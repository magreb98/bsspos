<?php

declare(strict_types=1);

namespace Modules\Commerce\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Commerce\Http\Resources\StockLevelResource;
use Modules\Commerce\Internal\Services\StockLevelQueryService;

final class StockController
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

        return response()->json(['data' => StockLevelResource::collection($levels)]);
    }
}
