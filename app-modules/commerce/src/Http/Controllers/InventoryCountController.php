<?php

declare(strict_types=1);

namespace Modules\Commerce\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Commerce\Internal\Models\InventoryCount;
use Modules\Commerce\Internal\Models\PointOfSale;
use Modules\Commerce\Internal\Services\InventoryService;

final class InventoryCountController
{
    public function __construct(private readonly InventoryService $service)
    {
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'point_of_sale_id'               => 'required|uuid',
            'notes'                          => 'nullable|string|max:1000',
            'lines'                          => 'required|array|min:1',
            'lines.*.product_id'             => 'required|uuid',
            'lines.*.counted_quantity'       => 'required|integer|min:0',
        ]);

        $pos = PointOfSale::where('id', $data['point_of_sale_id'])->firstOrFail();

        /** @var list<array{product_id: string, counted_quantity: int}> $lines */
        $lines = array_values($data['lines']);

        $inventoryCount = $this->service->count($pos, $lines, $data['notes'] ?? null);

        return response()->json(['data' => $inventoryCount->load('lines.product')], 201);
    }

    public function show(InventoryCount $inventoryCount): JsonResponse
    {
        return response()->json(['data' => $inventoryCount->load('lines.product', 'pointOfSale')]);
    }
}
