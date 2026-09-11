<?php

declare(strict_types=1);

namespace Modules\Commerce\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Commerce\Internal\Models\StockMovement;

final class StockMovementController
{
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

        $data = array_map(static function (StockMovement $m): array {
            return [
                'id'         => $m->id,
                'product'    => $m->product?->label ?? $m->product_id,
                'type'       => $m->quantity >= 0 ? 'entree' : 'sortie',
                'qty'        => $m->quantity,
                'reason'     => $m->sale_line_id !== null ? 'Vente' : 'Ajustement',
                'created_at' => $m->occurred_at?->toIso8601String() ?? $m->created_at?->toIso8601String(),
            ];
        }, $paginator->items());

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
}
