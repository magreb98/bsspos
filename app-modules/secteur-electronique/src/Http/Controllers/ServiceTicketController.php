<?php

declare(strict_types=1);

namespace Modules\SecteurElectronique\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\SecteurElectronique\Enums\SerialStatus;
use Modules\SecteurElectronique\Enums\TicketStatus;
use Modules\SecteurElectronique\Http\Requests\StoreServiceTicketRequest;
use Modules\SecteurElectronique\Http\Requests\UpdateServiceTicketRequest;
use Modules\SecteurElectronique\Http\Resources\ServiceTicketResource;
use Modules\SecteurElectronique\Http\Resources\ServiceTicketSummaryResource;
use Modules\SecteurElectronique\Models\SerialUnit;
use Modules\SecteurElectronique\Models\ServiceTicket;
use Modules\SecteurElectronique\Services\ServiceTicketService;

final class ServiceTicketController
{
    public function __construct(
        private readonly ServiceTicketService $service,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $query = ServiceTicket::query()
            ->with(['serialUnit.product'])
            ->orderByDesc('opened_at');

        if ($request->filled('serial_unit_id')) {
            $query->where('serial_unit_id', $request->input('serial_unit_id'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $paginator = $query->paginate(15);

        $data = array_map(fn (ServiceTicket $t) => new ServiceTicketSummaryResource($t), $paginator->items());

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

    public function store(StoreServiceTicketRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $unit = SerialUnit::query()->whereKey($validated['serial_unit_id'])->first();
        if ($unit === null) {
            return response()->json([
                'code'    => 'SERIAL_UNIT_NOT_FOUND',
                'message' => 'Cette unité sérialisée n\'existe pas.',
                'champ'   => null,
            ], 404);
        }

        if ($unit->status === SerialStatus::Sold) {
            return response()->json([
                'code'    => 'SERIAL_UNIT_NOT_AVAILABLE',
                'message' => 'Cette unité ne peut pas recevoir un ticket (statut : vendue).',
                'champ'   => 'serial_unit_id',
            ], 422);
        }

        $ticket = $this->service->open($unit, $validated['description']);

        return response()->json(['data' => new ServiceTicketResource($ticket)], 201);
    }

    public function show(ServiceTicket $serviceTicket): JsonResponse
    {
        return response()->json(['data' => new ServiceTicketResource($serviceTicket)]);
    }

    public function update(UpdateServiceTicketRequest $request, ServiceTicket $serviceTicket): JsonResponse
    {
        if ($serviceTicket->status === TicketStatus::Closed) {
            return response()->json([
                'code'    => 'TICKET_ALREADY_CLOSED',
                'message' => 'Ce ticket est déjà fermé.',
                'champ'   => null,
            ], 409);
        }

        $validated = $request->validated();

        $newStatus = isset($validated['status'])
            ? TicketStatus::from($validated['status'])
            : null;

        if ($newStatus === TicketStatus::Closed) {
            $preClose = [];

            if (isset($validated['description'])) {
                $preClose['description'] = $validated['description'];
            }

            if (array_key_exists('repair_cost', $validated)) {
                $preClose['repair_cost'] = $validated['repair_cost'];
            }

            if ($preClose !== []) {
                $serviceTicket->update($preClose);
            }

            $unit = $serviceTicket->serialUnit;
            if ($unit !== null) {
                $this->service->close($serviceTicket, $unit);
            }
        } else {
            $update = [];

            if ($newStatus !== null) {
                $update['status'] = $newStatus;
            }

            if (isset($validated['description'])) {
                $update['description'] = $validated['description'];
            }

            if (array_key_exists('repair_cost', $validated)) {
                $update['repair_cost'] = $validated['repair_cost'];
            }

            if ($update !== []) {
                $serviceTicket->update($update);
            }
        }

        $serviceTicket->refresh();

        return response()->json(['data' => new ServiceTicketResource($serviceTicket)]);
    }
}
