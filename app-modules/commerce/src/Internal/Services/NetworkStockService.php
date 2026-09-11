<?php

declare(strict_types=1);

namespace Modules\Commerce\Internal\Services;

use App\Platform\Identity\Models\User;
use Illuminate\Support\Facades\DB;
use Modules\Commerce\Internal\Enums\TransferStatus;
use Modules\Commerce\Internal\Models\Product;
use Modules\Commerce\Internal\Models\TransferLine;

/**
 * @phpstan-type PosStockEntry array{pos_id: string, pos_name: string, stock: int}
 * @phpstan-type NetworkBreakdown array{pos: list<PosStockEntry>, in_transit: int, total: int}
 */

final class NetworkStockService
{
    /**
     * Sum of all stock movements + all quantities currently in transit.
     * This total is invariant across dispatch and receive operations.
     */
    public function networkTotal(Product $product): int
    {
        $posStock = (int) DB::table('stock_movements')
            ->where('product_id', $product->id)
            ->sum('quantity');

        $inTransit = (int) TransferLine::whereHas(
            'transfer',
            fn ($q) => $q->where('status', TransferStatus::InTransit)
        )
            ->where('product_id', $product->id)
            ->sum('quantity');

        return $posStock + $inTransit;
    }

    /**
     * Stock on hand at a specific POS (movements only, excludes in-transit).
     */
    public function posStock(Product $product, string $posId): int
    {
        return (int) DB::table('stock_movements')
            ->where('product_id', $product->id)
            ->where('point_of_sale_id', $posId)
            ->sum('quantity');
    }

    /**
     * Per-POS stock breakdown for a product across the network.
     * Only POS with non-zero stock are included.
     *
     * @return NetworkBreakdown
     */
    public function networkBreakdown(Product $product): array
    {
        $rows = DB::table('stock_movements')
            ->join('points_of_sale', 'points_of_sale.id', '=', 'stock_movements.point_of_sale_id')
            ->where('stock_movements.product_id', $product->id)
            ->groupBy('stock_movements.point_of_sale_id', 'points_of_sale.name')
            ->havingRaw('SUM(stock_movements.quantity) <> 0')
            ->select([
                'stock_movements.point_of_sale_id as pos_id',
                'points_of_sale.name as pos_name',
                DB::raw('SUM(stock_movements.quantity) as stock'),
            ])
            ->orderBy('points_of_sale.name')
            ->get();

        $inTransit = (int) TransferLine::whereHas(
            'transfer',
            fn ($q) => $q->where('status', TransferStatus::InTransit)
        )
            ->where('product_id', $product->id)
            ->sum('quantity');

        /** @var list<PosStockEntry> $pos */
        $pos = array_values($rows->map(fn (object $row) => [
            'pos_id'   => (string) $row->pos_id,
            'pos_name' => (string) $row->pos_name,
            'stock'    => (int) $row->stock,
        ])->all());

        return [
            'pos'        => $pos,
            'in_transit' => $inTransit,
            'total'      => array_sum(array_column($pos, 'stock')) + $inTransit,
        ];
    }

    /**
     * Per-POS stock breakdown scoped to a user's visible perimeters.
     * Only POS whose organizational unit is linked to a visible perimeter are included.
     *
     * @return NetworkBreakdown
     */
    public function networkBreakdownForUser(Product $product, User $user): array
    {
        $visiblePerimeterIds = $user->visiblePerimeters();

        if ($visiblePerimeterIds->isEmpty()) {
            return ['pos' => [], 'in_transit' => 0, 'total' => 0];
        }

        $posIds = DB::table('points_of_sale')
            ->join('organizational_units', 'organizational_units.id', '=', 'points_of_sale.organizational_unit_id')
            ->whereIn('organizational_units.perimeter_id', $visiblePerimeterIds)
            ->pluck('points_of_sale.id');

        if ($posIds->isEmpty()) {
            return ['pos' => [], 'in_transit' => 0, 'total' => 0];
        }

        $rows = DB::table('stock_movements')
            ->join('points_of_sale', 'points_of_sale.id', '=', 'stock_movements.point_of_sale_id')
            ->where('stock_movements.product_id', $product->id)
            ->whereIn('stock_movements.point_of_sale_id', $posIds)
            ->groupBy('stock_movements.point_of_sale_id', 'points_of_sale.name')
            ->havingRaw('SUM(stock_movements.quantity) <> 0')
            ->select([
                'stock_movements.point_of_sale_id as pos_id',
                'points_of_sale.name as pos_name',
                DB::raw('SUM(stock_movements.quantity) as stock'),
            ])
            ->orderBy('points_of_sale.name')
            ->get();

        $inTransit = (int) TransferLine::whereHas(
            'transfer',
            fn ($q) => $q->where('status', TransferStatus::InTransit)
                ->whereIn('destination_pos_id', $posIds)
        )
            ->where('product_id', $product->id)
            ->sum('quantity');

        /** @var list<PosStockEntry> $pos */
        $pos = array_values($rows->map(fn (object $row) => [
            'pos_id'   => (string) $row->pos_id,
            'pos_name' => (string) $row->pos_name,
            'stock'    => (int) $row->stock,
        ])->all());

        return [
            'pos'        => $pos,
            'in_transit' => $inTransit,
            'total'      => array_sum(array_column($pos, 'stock')) + $inTransit,
        ];
    }
}
