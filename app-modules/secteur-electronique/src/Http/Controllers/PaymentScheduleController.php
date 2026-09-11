<?php

declare(strict_types=1);

namespace Modules\SecteurElectronique\Http\Controllers;

use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Commerce\Internal\Models\InvoiceSetting;
use Modules\Commerce\Internal\Models\Sale;
use Modules\SecteurElectronique\Http\Data\StorePaymentScheduleData;
use Modules\SecteurElectronique\Models\PaymentSchedule;
use Modules\SecteurElectronique\Services\PaymentScheduleService;
use Spatie\LaravelPdf\Facades\Pdf;
use Symfony\Component\HttpFoundation\Response;

final class PaymentScheduleController
{
    public function __construct(
        private readonly PaymentScheduleService $service,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $query = PaymentSchedule::with(['installments', 'sale.customer']);

        if ($request->filled('sale_id')) {
            $query->where('sale_id', $request->input('sale_id'));
        }

        $paginator = $query->paginate(15);

        $data = array_map(fn (PaymentSchedule $s) => $this->serializeSchedule($s), $paginator->items());

        return response()->json([
            'data' => $data,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page'     => $paginator->perPage(),
                'total'        => $paginator->total(),
                'last_page'    => $paginator->lastPage(),
            ],
        ]);
    }

    /** @return array<string, mixed> */
    private function serializeSchedule(PaymentSchedule $s): array
    {
        $installments    = $s->installments;
        $paidInstallments = $installments->filter(fn ($i) => $i->paid_at !== null);
        $paidAmount      = $paidInstallments->sum('amount');
        $count           = $installments->count();
        $paidCount       = $paidInstallments->count();

        $now = now();
        $hasOverdue = $installments
            ->filter(fn ($i) => $i->paid_at === null && $now->isAfter($i->due_on))
            ->isNotEmpty();

        $status = match (true) {
            $count > 0 && $paidCount === $count => 'completed',
            $hasOverdue                          => 'overdue',
            default                              => 'active',
        };

        $nextInstallment = $installments
            ->filter(fn ($i) => $i->paid_at === null)
            ->sortBy('due_on')
            ->first();

        return [
            ...$s->toArray(),
            'total_amount'             => $s->total,
            'paid_amount'              => $paidAmount,
            'installments_count'       => $count,
            'paid_installments_count'  => $paidCount,
            'status'                   => $status,
            'next_installment_date'    => $nextInstallment?->due_on,
            'sale'                     => $s->sale ? [
                'id'     => $s->sale->id,
                'number' => $s->sale->number,
            ] : null,
            'client' => $s->sale?->customer ? [
                'id'   => $s->sale->customer->id,
                'name' => $s->sale->customer->name,
            ] : null,
        ];
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'sale_id'               => ['required', 'uuid'],
            'deposit'               => ['required', 'integer', 'min:0'],
            'installments'          => ['required', 'array', 'min:1'],
            'installments.*.amount' => ['required', 'integer', 'min:1'],
            'installments.*.due_on' => ['required', 'string', 'date_format:Y-m-d'],
        ]);

        $sale = Sale::query()->whereKey($validated['sale_id'])->first();
        if ($sale === null) {
            return response()->json([
                'code'    => 'SALE_NOT_FOUND',
                'message' => 'Cette vente n\'existe pas.',
                'champ'   => null,
            ], 404);
        }

        if (PaymentSchedule::where('sale_id', $sale->id)->exists()) {
            return response()->json([
                'code'    => 'PAYMENT_SCHEDULE_ALREADY_EXISTS',
                'message' => 'Un échelonnement existe déjà pour cette vente.',
                'champ'   => null,
            ], 409);
        }

        $_ = StorePaymentScheduleData::from([
            'sale_id'      => $validated['sale_id'],
            'deposit'      => $validated['deposit'],
            'installments' => $validated['installments'],
        ]);

        try {
            $schedule = $this->service->create($sale, (int) $validated['deposit'], $validated['installments']);
        } catch (DomainException) {
            return response()->json([
                'code'    => 'PAYMENT_SCHEDULE_INVALID_TOTAL',
                'message' => 'La somme des versements et de l\'acompte ne correspond pas au total de la vente.',
                'champ'   => null,
            ], 422);
        }

        $schedule->load('installments');

        return response()->json(['data' => $schedule->toArray()], 201);
    }

    public function show(PaymentSchedule $paymentSchedule): JsonResponse
    {
        $paymentSchedule->load('installments');

        return response()->json(['data' => $paymentSchedule->toArray()]);
    }

    public function pdf(PaymentSchedule $paymentSchedule): Response
    {
        $paymentSchedule->load('installments', 'sale.customer');
        $setting = InvoiceSetting::first();

        /** @phpstan-ignore return.type */
        return Pdf::view('pdf.payment-schedule', ['schedule' => $paymentSchedule, 'setting' => $setting])
            ->name("schedule-{$paymentSchedule->id}.pdf")
            ->download();
    }
}
