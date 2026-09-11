<?php

declare(strict_types=1);

namespace Modules\Commerce\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\Commerce\Internal\Enums\InvoiceStatus;
use Modules\Commerce\Internal\Enums\SaleState;
use Modules\Commerce\Internal\Models\Invoice;
use Modules\Commerce\Internal\Models\InvoicePayment;
use Modules\Commerce\Internal\Models\InvoiceSetting;
use Modules\Commerce\Internal\Models\Sale;
use Spatie\LaravelPdf\Facades\Pdf;
use Symfony\Component\HttpFoundation\Response;

final class InvoiceController
{
    public function index(Request $request): JsonResponse
    {
        $query = Invoice::with('customer')->latest('issue_date');

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('client_id')) {
            $query->where('client_id', $request->input('client_id'));
        }

        if ($request->boolean('overdue')) {
            $query->whereNotIn('status', [InvoiceStatus::Paid->value])
                ->whereNotNull('due_date')
                ->whereDate('due_date', '<', now());
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
            'client_id'  => ['required', 'uuid'],
            'issue_date' => ['sometimes', 'date'],
            'due_date'   => ['sometimes', 'nullable', 'date', 'after_or_equal:issue_date'],
            'total_ht'   => ['required', 'integer', 'min:0'],
            'total_vat'  => ['sometimes', 'integer', 'min:0'],
            'total_ttc'  => ['required', 'integer', 'min:1'],
            'notes'      => ['sometimes', 'nullable', 'string', 'max:2000'],
        ]);

        $invoice = DB::transaction(function () use ($validated): Invoice {
            /** @var int $lastSeq */
            $lastSeq = (int) Invoice::lockForUpdate()->max('number_sequence');
            $seq     = $lastSeq + 1;
            $ttc     = (int) $validated['total_ttc'];

            return Invoice::create([
                'number_sequence'    => $seq,
                'number'             => 'FAC-' . date('Y') . '-' . str_pad((string) $seq, 4, '0', STR_PAD_LEFT),
                'status'             => InvoiceStatus::Draft,
                'client_id'          => $validated['client_id'],
                'issue_date'         => $validated['issue_date'] ?? today()->toDateString(),
                'due_date'           => $validated['due_date'] ?? null,
                'total_ht'           => (int) $validated['total_ht'],
                'total_vat'          => (int) ($validated['total_vat'] ?? 0),
                'total_ttc'          => $ttc,
                'paid_amount'        => 0,
                'outstanding_amount' => $ttc,
                'notes'              => $validated['notes'] ?? null,
            ]);
        });

        return response()->json(['data' => $invoice->load('customer')->toArray()], 201);
    }

    public function show(Invoice $invoice): JsonResponse
    {
        $invoice->load(['customer', 'sale.lines.product', 'payments']);
        $setting = InvoiceSetting::first();

        return response()->json([
            'data' => array_merge($invoice->toArray(), [
                'setting' => $setting?->toArray(),
            ]),
        ]);
    }

    public function fromSale(Sale $sale): JsonResponse
    {
        if ($sale->state !== SaleState::Confirmed) {
            return response()->json(['code' => 'SALE_NOT_CONFIRMED', 'message' => 'Only confirmed sales can generate an invoice.', 'champ' => null], 409);
        }

        if ($sale->client_id === null) {
            return response()->json(['code' => 'NO_CUSTOMER', 'message' => 'Sale has no customer — cannot generate a B2B invoice.', 'champ' => null], 422);
        }

        $existing = Invoice::where('sale_id', $sale->id)->first();

        if ($existing !== null) {
            return response()->json(['code' => 'INVOICE_EXISTS', 'message' => 'An invoice already exists for this sale.', 'champ' => null], 409);
        }

        $invoice = DB::transaction(function () use ($sale): Invoice {
            /** @var int $lastSeq */
            $lastSeq = (int) Invoice::lockForUpdate()->max('number_sequence');
            $seq     = $lastSeq + 1;
            $ht      = $sale->total_excluding_tax?->toInt() ?? 0;
            $vat     = $sale->total_tax?->toInt() ?? 0;
            $ttc     = $sale->total_including_tax?->toInt() ?? 0;

            return Invoice::create([
                'number_sequence'    => $seq,
                'number'             => 'FAC-' . date('Y') . '-' . str_pad((string) $seq, 4, '0', STR_PAD_LEFT),
                'status'             => InvoiceStatus::Sent,
                'client_id'          => $sale->client_id,
                'sale_id'            => $sale->id,
                'issue_date'         => today()->toDateString(),
                'due_date'           => null,
                'total_ht'           => $ht,
                'total_vat'          => $vat,
                'total_ttc'          => $ttc,
                'paid_amount'        => 0,
                'outstanding_amount' => $ttc,
                'notes'              => null,
            ]);
        });

        return response()->json(['data' => $invoice->load('customer', 'sale')->toArray()], 201);
    }

    public function markSent(Invoice $invoice): JsonResponse
    {
        if ($invoice->status !== InvoiceStatus::Draft) {
            return response()->json(['code' => 'NOT_DRAFT', 'message' => 'Only draft invoices can be marked as sent.', 'champ' => null], 409);
        }

        $invoice->update(['status' => InvoiceStatus::Sent]);

        return response()->json(['data' => $invoice->fresh()?->toArray() ?? []]);
    }

    public function recordPayment(Request $request, Invoice $invoice): JsonResponse
    {
        if ($invoice->status === InvoiceStatus::Paid) {
            return response()->json(['code' => 'ALREADY_PAID', 'message' => 'Invoice is already fully paid.', 'champ' => null], 409);
        }

        $validated = $request->validate([
            'amount'       => ['required', 'integer', 'min:1'],
            'payment_date' => ['sometimes', 'date'],
            'method'       => ['sometimes', 'string', 'max:50'],
            'reference'    => ['sometimes', 'nullable', 'string', 'max:100'],
        ]);

        DB::transaction(function () use ($invoice, $validated): void {
            $locked = Invoice::where('id', $invoice->id)->lockForUpdate()->firstOrFail();

            InvoicePayment::create([
                'invoice_id'   => $locked->id,
                'amount'       => (int) $validated['amount'],
                'payment_date' => $validated['payment_date'] ?? today()->toDateString(),
                'method'       => $validated['method'] ?? 'cash',
                'reference'    => $validated['reference'] ?? null,
            ]);

            $newPaid      = ($locked->paid_amount?->toInt() ?? 0) + (int) $validated['amount'];
            $total        = $locked->total_ttc?->toInt() ?? 0;
            $outstanding  = max(0, $total - $newPaid);
            $newStatus    = $outstanding === 0
                ? InvoiceStatus::Paid
                : ($newPaid > 0 ? InvoiceStatus::PartiallyPaid : $locked->status);

            $locked->update([
                'paid_amount'        => $newPaid,
                'outstanding_amount' => $outstanding,
                'status'             => $newStatus,
            ]);
        });

        return response()->json(['data' => $invoice->fresh()?->load('payments')->toArray() ?? []]);
    }

    public function pdf(Invoice $invoice, \Illuminate\Http\Request $request): Response
    {
        $invoice->load(['customer', 'payments', 'sale.lines']);
        $setting = InvoiceSetting::first();

        return Pdf::view('pdf.invoice', compact('invoice', 'setting'))
            ->name("invoice-{$invoice->number}.pdf")
            ->download()
            ->toResponse($request);
    }
}
