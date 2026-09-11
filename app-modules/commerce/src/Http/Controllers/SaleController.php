<?php

declare(strict_types=1);

namespace Modules\Commerce\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Commerce\Internal\Enums\SaleState;
use Spatie\LaravelPdf\Facades\Pdf;
use Symfony\Component\HttpFoundation\Response;
use Modules\Commerce\Internal\Models\CashSession;
use Modules\Commerce\Internal\Models\Customer;
use Modules\Commerce\Internal\Models\CustomerCredit;
use Modules\Commerce\Internal\Models\InvoiceSetting;
use Modules\Commerce\Internal\Models\Product;
use Modules\Commerce\Internal\Models\Sale;
use Modules\Commerce\Internal\Models\SaleLine;
use Modules\Commerce\Internal\Services\PromotionEngine;
use Modules\Commerce\Internal\Services\SaleConfirmationService;

final class SaleController
{
    public function __construct(
        private readonly SaleConfirmationService $service,
        private readonly PromotionEngine $promotionEngine,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $query = Sale::query()
            ->with(['customer', 'cashSession.openedBy'])
            ->withCount('lines')
            ->latest('created_at')
            ->where('state', '!=', SaleState::Quote->value);

        // Row-level security: users without management privileges see only their own sessions
        $user = Auth::user();

        if ($user !== null && ! $user->can('inventory.write')) {
            $query->whereHas('cashSession', fn ($q) => $q->where('opened_by', (string) $user->getAuthIdentifier()));
        }

        if ($request->filled('state')) {
            $query->where('state', $request->input('state'));
        }

        if ($request->filled('cash_session_id')) {
            $query->where('cash_session_id', $request->input('cash_session_id'));
        }

        if ($request->filled('client_id')) {
            $query->where('client_id', $request->input('client_id'));
        }

        if ($request->filled('from')) {
            $query->whereDate('created_at', '>=', $request->input('from'));
        }

        if ($request->filled('to')) {
            $query->whereDate('created_at', '<=', $request->input('to'));
        }

        $paginator = $query->paginate(20);

        return response()->json([
            'data' => $paginator->items(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page'     => $paginator->perPage(),
                'total'        => $paginator->total(),
                'last_page'    => $paginator->lastPage(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'cash_session_id' => ['required', 'uuid'],
            'client_id'       => ['sometimes', 'nullable', 'uuid'],
            'idempotency_key' => ['sometimes', 'nullable', 'string', 'max:100'],
        ]);

        $session = CashSession::query()->whereKey($validated['cash_session_id'])->first();
        if ($session === null) {
            return response()->json(['code' => 'SESSION_NOT_FOUND', 'message' => 'Session introuvable.', 'champ' => 'cash_session_id'], 404);
        }

        if (! $session->isOpen()) {
            return response()->json(['code' => 'SESSION_CLOSED', 'message' => 'La session est fermée.', 'champ' => 'cash_session_id'], 409);
        }

        if (isset($validated['idempotency_key'])) {
            $existing = Sale::query()->where('idempotency_key', $validated['idempotency_key'])->first();
            if ($existing !== null) {
                return response()->json(['data' => $existing->load('lines')->toArray()]);
            }
        }

        $sale = Sale::create([
            'cash_session_id' => $validated['cash_session_id'],
            'client_id'       => $validated['client_id'] ?? null,
            'idempotency_key' => $validated['idempotency_key'] ?? (string) Str::uuid(),
            'state'           => SaleState::Draft,
        ]);

        return response()->json(['data' => $sale->toArray()], 201);
    }

    public function show(Sale $sale): JsonResponse
    {
        return response()->json(['data' => $sale->load('lines', 'customer')->toArray()]);
    }

    public function cancel(Sale $sale): JsonResponse
    {
        if ($sale->state !== SaleState::Draft) {
            return response()->json(['code' => 'SALE_NOT_DRAFT', 'message' => 'Seule une vente en brouillon peut être annulée.', 'champ' => null], 409);
        }

        $sale->update(['state' => SaleState::Abandoned]);

        return response()->json(['data' => $sale->fresh()?->toArray() ?? $sale->toArray()]);
    }

    public function receipt(Sale $sale): JsonResponse
    {
        $sale->load('lines.product', 'customer', 'payments');

        $setting = InvoiceSetting::first();

        $lines = $sale->lines->map(static fn (SaleLine $l): array => [
            'designation'              => $l->designation,
            'quantity'                 => $l->quantity,
            'unit_price'               => $l->unit_price?->toInt() ?? 0,
            'vat_rate'                 => (string) $l->vat_rate,
            'line_total_excluding_tax' => $l->line_total_excluding_tax?->toInt() ?? 0,
            'line_total_tax'           => $l->line_total_tax?->toInt() ?? 0,
            'line_total_including_tax' => $l->line_total_including_tax?->toInt() ?? 0,
            'discount_amount'          => $l->discount_amount?->toInt() ?? 0,
        ]);

        $payments = $sale->payments->map(static fn ($p): array => [
            'method'       => $p->method instanceof \BackedEnum ? $p->method->value : $p->method,
            'amount'       => $p->amount?->toInt() ?? 0,
            'status'       => $p->status->value,
            'reference'    => $p->reference,
            'confirmed_at' => $p->confirmed_at?->toIso8601String(),
        ]);

        return response()->json([
            'data' => [
                'company' => $setting !== null ? [
                    'name'         => $setting->company_name,
                    'niu'          => $setting->niu,
                    'rccm'         => $setting->rccm,
                    'address'      => $setting->address,
                    'phone'        => $setting->phone,
                ] : null,
                'sale' => [
                    'id'                  => $sale->id,
                    'number'              => $sale->number,
                    'state'               => $sale->state->value,
                    'confirmed_at'        => $sale->confirmed_at?->toIso8601String(),
                    'total_excluding_tax' => $sale->total_excluding_tax?->toInt() ?? 0,
                    'total_tax'           => $sale->total_tax?->toInt() ?? 0,
                    'total_including_tax' => $sale->total_including_tax?->toInt() ?? 0,
                ],
                'customer' => $sale->customer?->toArray(),
                'lines'    => $lines->all(),
                'payments' => $payments->all(),
            ],
        ]);
    }

    public function receiptPdf(Sale $sale): Response
    {
        $sale->load('lines.product', 'customer', 'payments');
        $setting = InvoiceSetting::first();

        /** @phpstan-ignore return.type */
        return Pdf::view('pdf.receipt', compact('sale', 'setting'))
            ->name("receipt-{$sale->number}.pdf")
            ->download();
    }

    public function confirm(Request $request, Sale $sale): JsonResponse
    {
        if ($sale->state !== SaleState::Draft) {
            return response()->json(['code' => 'SALE_NOT_DRAFT', 'message' => 'Seule une vente en brouillon peut être confirmée.', 'champ' => null], 409);
        }

        $validated = $request->validate([
            'coupon_code'          => ['sometimes', 'nullable', 'string'],
            'loyalty_points_used'  => ['sometimes', 'integer', 'min:0'],
            'customer_credit_id'   => ['sometimes', 'nullable', 'uuid'],
        ]);

        $couponCode          = $validated['coupon_code'] ?? null;
        $loyaltyPointsUsed   = (int) ($validated['loyalty_points_used'] ?? 0);
        $customerCreditId    = $validated['customer_credit_id'] ?? null;

        try {
            DB::transaction(function () use ($sale, $couponCode, $loyaltyPointsUsed, $customerCreditId): void {
                // Apply customer credit
                if ($customerCreditId !== null) {
                    $credit = CustomerCredit::where('id', $customerCreditId)
                        ->where('customer_id', $sale->client_id)
                        ->lockForUpdate()
                        ->first();

                    if ($credit === null) {
                        throw new \DomainException('Credit note not found for this customer.');
                    }

                    if ($credit->isExhausted()) {
                        throw new \DomainException('Credit note is already exhausted.');
                    }

                    $credit->decrement('remaining_amount', min($credit->remaining_amount?->toInt() ?? 0, $sale->total_including_tax?->toInt() ?? 0));
                }

                // Apply loyalty point debit
                if ($loyaltyPointsUsed > 0 && $sale->client_id !== null) {
                    $customer = Customer::where('id', $sale->client_id)
                        ->lockForUpdate()
                        ->first();

                    if ($customer === null || ($customer->loyalty_points ?? 0) < $loyaltyPointsUsed) {
                        throw new \DomainException("Insufficient loyalty points.");
                    }

                    $customer->decrement('loyalty_points', $loyaltyPointsUsed);
                    $sale->update(['loyalty_points_used' => $loyaltyPointsUsed]);
                }

                $this->promotionEngine->apply($sale, $couponCode !== null ? (string) $couponCode : null);
                $this->service->confirm($sale);
            });
        } catch (\DomainException $e) {
            return response()->json(['code' => 'COUPON_ERROR', 'message' => $e->getMessage(), 'champ' => 'coupon_code'], 422);
        }

        return response()->json(['data' => $sale->fresh()?->load('lines')->toArray() ?? []]);
    }

    public function addLine(Request $request, Sale $sale): JsonResponse
    {
        if ($sale->state !== SaleState::Draft) {
            return response()->json(['code' => 'SALE_NOT_DRAFT', 'message' => 'Impossible d\'ajouter une ligne à une vente confirmée.', 'champ' => null], 409);
        }

        $validated = $request->validate([
            'product_id'      => ['required', 'uuid'],
            'quantity'        => ['required', 'integer', 'min:1'],
            'unit_price'      => ['sometimes', 'integer', 'min:0'],
            'designation'     => ['sometimes', 'nullable', 'string', 'max:255'],
            'discount_percent' => ['sometimes', 'integer', 'min:0', 'max:100'],
            'discount_amount'  => ['sometimes', 'integer', 'min:0'],
        ]);

        if (isset($validated['discount_percent']) && isset($validated['discount_amount'])) {
            return response()->json(['code' => 'DISCOUNT_CONFLICT', 'message' => 'Spécifiez discount_percent ou discount_amount, pas les deux.', 'champ' => 'discount_percent'], 422);
        }

        $product = Product::query()->whereKey($validated['product_id'])->first();
        if ($product === null) {
            return response()->json(['code' => 'PRODUCT_NOT_FOUND', 'message' => 'Produit introuvable.', 'champ' => 'product_id'], 404);
        }

        $unitPrice  = $validated['unit_price'] ?? ($product->selling_price?->toInt() ?? 0);
        $quantity   = (int) $validated['quantity'];
        $baseTotal  = $unitPrice * $quantity;

        // Compute discount
        $discountAmount = 0;

        if (isset($validated['discount_percent'])) {
            $discountAmount = (int) floor($baseTotal * $validated['discount_percent'] / 100);
        } elseif (isset($validated['discount_amount'])) {
            $discountAmount = min((int) $validated['discount_amount'], $baseTotal);
        }

        $vatRateStr = (string) $product->vat_rate;
        $vatBp      = (int) round((float) $vatRateStr * 100);
        $ht         = $baseTotal - $discountAmount;
        $tax        = (int) round($ht * $vatBp / 10000);
        $ttc        = $ht + $tax;

        $line = SaleLine::create([
            'sale_id'                  => $sale->id,
            'product_id'               => $product->id,
            'designation'              => $validated['designation'] ?? $product->label,
            'unit_price'               => $unitPrice,
            'vat_rate'                 => $product->vat_rate,
            'quantity'                 => $quantity,
            'discount_amount'          => $discountAmount,
            'line_total_excluding_tax' => $ht,
            'line_total_tax'           => $tax,
            'line_total_including_tax' => $ttc,
            'allocations'              => [],
        ]);

        return response()->json(['data' => $line->toArray()], 201);
    }

    public function removeLine(Sale $sale, SaleLine $line): JsonResponse
    {
        if ($sale->state !== SaleState::Draft) {
            return response()->json(['code' => 'SALE_NOT_DRAFT', 'message' => 'Impossible de supprimer une ligne d\'une vente confirmée.', 'champ' => null], 409);
        }

        if ($line->sale_id !== $sale->id) {
            return response()->json(['code' => 'LINE_NOT_FOUND', 'message' => 'Ligne introuvable sur cette vente.', 'champ' => null], 404);
        }

        $line->delete();

        return response()->json(null, 204);
    }
}
