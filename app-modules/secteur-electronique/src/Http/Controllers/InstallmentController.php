<?php

declare(strict_types=1);

namespace Modules\SecteurElectronique\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\SecteurElectronique\Http\Resources\InstallmentResource;
use Modules\SecteurElectronique\Models\Installment;
use Modules\SecteurElectronique\Services\PaymentScheduleService;

final class InstallmentController
{
    public function __construct(
        private readonly PaymentScheduleService $service,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $query = Installment::query()->orderBy('due_on');

        if ($request->filled('payment_schedule_id')) {
            $query->where('payment_schedule_id', $request->input('payment_schedule_id'));
        }

        if ($request->boolean('overdue')) {
            $query->whereNull('paid_at')->whereDate('due_on', '<', today());
        }

        $paginator = $query->paginate(15);

        return response()->json([
            'data' => array_map(fn (Installment $i) => new InstallmentResource($i), $paginator->items()),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page'     => $paginator->perPage(),
                'total'        => $paginator->total(),
                'last_page'    => $paginator->lastPage(),
            ],
        ]);
    }

    public function show(Installment $installment): JsonResponse
    {
        return response()->json(['data' => new InstallmentResource($installment)]);
    }

    public function pay(Installment $installment): JsonResponse
    {
        if ($installment->paid_at !== null) {
            return response()->json([
                'code'    => 'INSTALLMENT_ALREADY_PAID',
                'message' => 'Ce versement a déjà été enregistré comme payé.',
                'champ'   => null,
            ], 409);
        }

        $this->service->payInstallment($installment);

        return response()->json(['data' => new InstallmentResource($installment->refresh())]);
    }
}
