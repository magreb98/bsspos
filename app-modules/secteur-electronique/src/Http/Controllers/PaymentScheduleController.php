<?php

declare(strict_types=1);

namespace Modules\SecteurElectronique\Http\Controllers;

use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Commerce\Internal\Models\InvoiceSetting;
use Modules\Commerce\Internal\Models\Sale;
use Modules\SecteurElectronique\Http\Requests\StorePaymentScheduleRequest;
use Modules\SecteurElectronique\Http\Resources\PaymentScheduleResource;
use Modules\SecteurElectronique\Http\Resources\PaymentScheduleSummaryResource;
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

        $data = array_map(fn (PaymentSchedule $s) => new PaymentScheduleSummaryResource($s), $paginator->items());

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

    public function store(StorePaymentScheduleRequest $request): JsonResponse
    {
        $validated = $request->validated();

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

        return response()->json(['data' => new PaymentScheduleResource($schedule)], 201);
    }

    public function show(PaymentSchedule $paymentSchedule): JsonResponse
    {
        $paymentSchedule->load('installments');

        return response()->json(['data' => new PaymentScheduleResource($paymentSchedule)]);
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
