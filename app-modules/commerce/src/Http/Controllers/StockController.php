<?php

declare(strict_types=1);

namespace Modules\Commerce\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Commerce\Http\Resources\StockLevelResource;
use Modules\Commerce\Internal\Models\StockLevel;

final class StockController
{
    public function index(Request $request): JsonResponse
    {
        $query = StockLevel::query()->with(['product', 'pointOfSale']);

        if ($request->filled('point_of_sale_id')) {
            $query->where('point_of_sale_id', $request->input('point_of_sale_id'));
        }

        if ($request->filled('product_id')) {
            $query->where('product_id', $request->input('product_id'));
        }

        $levels = $query->get();

        return response()->json(['data' => StockLevelResource::collection($levels)]);
    }
}
