<?php

declare(strict_types=1);

namespace Modules\Commerce\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Commerce\Http\Requests\SetStockThresholdRequest;
use Modules\Commerce\Http\Resources\StockLevelResource;
use Modules\Commerce\Internal\Models\StockLevel;
use Modules\Commerce\Internal\Services\StockLevelQueryService;

final class StockAlertController
{
    public function __construct(private readonly StockLevelQueryService $service)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $levels = $this->service->compute(
            $request->filled('point_of_sale_id') ? (string) $request->input('point_of_sale_id') : null,
            $request->filled('product_id') ? (string) $request->input('product_id') : null,
        );

        $alerts = $levels
            ->filter(fn (StockLevel $level): bool => $level->minimum_quantity > 0 && $level->quantity < $level->minimum_quantity)
            ->sortBy('quantity')
            ->values();

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
