<?php

declare(strict_types=1);

namespace Modules\Commerce\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Commerce\Http\Requests\StoreStockMovementRequest;
use Modules\Commerce\Http\Resources\StockMovementReportResource;
use Modules\Commerce\Internal\Models\PointOfSale;
use Modules\Commerce\Internal\Models\Product;
use Modules\Commerce\Internal\Models\StockMovement;

final class StockMovementController
{
    public function store(StoreStockMovementRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $product = Product::query()->whereKey($validated['product_id'])->first();
        if ($product === null) {
            return response()->json([
                'code'    => 'PRODUCT_NOT_FOUND',
                'message' => 'Ce produit n\'existe pas.',
                'champ'   => 'product_id',
            ], 404);
        }

        $pos = PointOfSale::query()->whereKey($validated['point_of_sale_id'])->first();
        if ($pos === null) {
            return response()->json([
                'code'    => 'POS_NOT_FOUND',
                'message' => 'Ce point de vente n\'existe pas.',
                'champ'   => 'point_of_sale_id',
            ], 404);
        }

        $movement = StockMovement::create([
            'point_of_sale_id' => $pos->id,
            'product_id'       => $product->id,
            'sale_line_id'     => null,
            'quantity'         => $validated['quantity'],
            'occurred_at'      => now(),
        ]);

        return response()->json(['data' => new StockMovementReportResource($movement->load('product', 'pointOfSale'))], 201);
    }

    public function index(Request $request): JsonResponse
    {
        $query = StockMovement::query()
            ->with('product', 'pointOfSale')
            ->latest('occurred_at');

        if ($request->filled('product_id')) {
            $query->where('product_id', $request->input('product_id'));
        }

        if ($request->filled('point_of_sale_id')) {
            $query->where('point_of_sale_id', $request->input('point_of_sale_id'));
        }

        if ($request->filled('from')) {
            $query->whereDate('occurred_at', '>=', $request->input('from'));
        }

        if ($request->filled('to')) {
            $query->whereDate('occurred_at', '<=', $request->input('to'));
        }

        // Filter by direction: 'in' (positive), 'out' (negative)
        if ($request->filled('direction')) {
            if ($request->input('direction') === 'in') {
                $query->where('quantity', '>', 0);
            } elseif ($request->input('direction') === 'out') {
                $query->where('quantity', '<', 0);
            }
        }

        $paginator = $query->paginate(50);

        return response()->json([
            'data' => StockMovementReportResource::collection($paginator->items()),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page'     => $paginator->perPage(),
                'total'        => $paginator->total(),
                'last_page'    => $paginator->lastPage(),
            ],
        ]);
    }
}
