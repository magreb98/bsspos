<?php

declare(strict_types=1);

namespace Modules\SecteurElectronique\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Commerce\Internal\Models\SaleLine;
use Modules\SecteurElectronique\Enums\SerialStatus;
use Modules\SecteurElectronique\Http\Data\StoreWarrantyData;
use Modules\SecteurElectronique\Models\SerialUnit;
use Modules\SecteurElectronique\Models\Warranty;
use Modules\SecteurElectronique\Services\WarrantyService;

final class WarrantyController
{
    public function __construct(
        private readonly WarrantyService $service,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $query = Warranty::query();

        if ($request->filled('serial_unit_id')) {
            $query->where('serial_unit_id', $request->input('serial_unit_id'));
        }

        $paginator = $query->paginate(15);

        return response()->json([
            'data' => array_map(fn (Warranty $w) => $w->toArray(), $paginator->items()),
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
            'serial_unit_id'  => ['required', 'uuid'],
            'sale_line_id'    => ['required', 'uuid'],
            'duration_months' => ['required', 'integer', 'min:1'],
        ]);

        $unit = SerialUnit::query()->whereKey($validated['serial_unit_id'])->first();
        if ($unit === null) {
            return response()->json([
                'code'    => 'SERIAL_UNIT_NOT_FOUND',
                'message' => 'Cette unité sérialisée n\'existe pas.',
                'champ'   => null,
            ], 404);
        }

        if ($unit->status !== SerialStatus::Sold) {
            return response()->json([
                'code'    => 'WARRANTY_UNIT_NOT_SOLD',
                'message' => 'Cette unité n\'est pas vendue — impossible d\'émettre une garantie.',
                'champ'   => 'serial_unit_id',
            ], 422);
        }

        if (Warranty::where('serial_unit_id', $unit->id)->exists()) {
            return response()->json([
                'code'    => 'WARRANTY_ALREADY_EXISTS',
                'message' => 'Une garantie existe déjà pour cette unité.',
                'champ'   => null,
            ], 409);
        }

        $line = SaleLine::query()->whereKey($validated['sale_line_id'])->first();
        if ($line === null) {
            return response()->json([
                'code'    => 'SALE_LINE_NOT_FOUND',
                'message' => 'Cette ligne de vente n\'existe pas.',
                'champ'   => null,
            ], 404);
        }

        $_ = StoreWarrantyData::from($validated);

        $warranty = $this->service->issue($unit, $line, (int) $validated['duration_months']);

        return response()->json(['data' => $warranty->toArray()], 201);
    }

    public function show(Warranty $warranty): JsonResponse
    {
        return response()->json(['data' => $warranty->toArray()]);
    }
}
