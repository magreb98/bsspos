<?php

declare(strict_types=1);

namespace Modules\Commerce\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Modules\Commerce\Http\Requests\StoreInventoryCountRequest;
use Modules\Commerce\Http\Resources\InventoryCountResource;
use Modules\Commerce\Internal\Models\InventoryCount;
use Modules\Commerce\Internal\Models\PointOfSale;
use Modules\Commerce\Internal\Services\InventoryService;

final class InventoryCountController
{
    public function __construct(private readonly InventoryService $service)
    {
    }

    public function store(StoreInventoryCountRequest $request): JsonResponse
    {
        $data = $request->validated();

        $pos = PointOfSale::where('id', $data['point_of_sale_id'])->firstOrFail();

        /** @var list<array{product_id: string, counted_quantity: int}> $lines */
        $lines = array_values($data['lines']);

        $inventoryCount = $this->service->count($pos, $lines, $data['notes'] ?? null);

        return response()->json(['data' => new InventoryCountResource($inventoryCount->load('lines.product'))], 201);
    }

    public function show(InventoryCount $inventoryCount): JsonResponse
    {
        return response()->json(['data' => new InventoryCountResource($inventoryCount->load('lines.product', 'pointOfSale'))]);
    }
}
