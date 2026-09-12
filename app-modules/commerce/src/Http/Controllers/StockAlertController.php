<?php

declare(strict_types=1);

namespace Modules\Commerce\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Commerce\Http\Requests\SetStockThresholdRequest;
use Modules\Commerce\Http\Resources\StockLevelResource;
use Modules\Commerce\Internal\Models\StockLevel;

final class StockAlertController
{
    public function index(Request $request): JsonResponse
    {
        $query = StockLevel::query()
            ->with('product', 'pointOfSale')
            ->whereColumn('quantity', '<', 'minimum_quantity')
            ->where('minimum_quantity', '>', 0);

        if ($request->filled('point_of_sale_id')) {
            $query->where('point_of_sale_id', $request->input('point_of_sale_id'));
        }

        if ($request->filled('product_id')) {
            $query->where('product_id', $request->input('product_id'));
        }

        $alerts = $query->orderBy('quantity')->get();

        return response()->json(['data' => StockLevelResource::collection($alerts)]);
    }

    public function setThreshold(SetStockThresholdRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $level = StockLevel::firstOrCreate(
            [
                'product_id'       => $validated['product_id'],
                'point_of_sale_id' => $validated['point_of_sale_id'],
            ],
            ['quantity' => 0, 'minimum_quantity' => 0],
        );

        $level->update(['minimum_quantity' => $validated['minimum_quantity']]);

        return response()->json(['data' => new StockLevelResource($level->fresh() ?? $level)]);
    }
}
