<?php

declare(strict_types=1);

namespace Modules\Commerce\Internal\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\Commerce\Internal\Models\PointOfSale;
use Modules\Commerce\Internal\Models\Product;
use Modules\Commerce\Internal\Models\StockLevel;

/**
 * Computes live stock levels from the stock_movements ledger — the
 * authoritative source of truth used everywhere else (NetworkStockService,
 * InventoryService, SaleConfirmationService, ReceptionService, TransferService).
 *
 * The stock_levels table only stores the per product/POS minimum_quantity
 * threshold configured via StockAlertController::setThreshold(); its own
 * `quantity` column is never kept in sync with the ledger and must not be
 * read as the current stock.
 */
final class StockLevelQueryService
{
    /**
     * @return Collection<int, StockLevel>
     */
    public function compute(?string $pointOfSaleId = null, ?string $productId = null): Collection
    {
        $movementQuery = DB::table('stock_movements')
            ->select('product_id', 'point_of_sale_id', DB::raw('SUM(quantity) as net_quantity'))
            ->groupBy('product_id', 'point_of_sale_id');

        if ($pointOfSaleId !== null) {
            $movementQuery->where('point_of_sale_id', $pointOfSaleId);
        }

        if ($productId !== null) {
            $movementQuery->where('product_id', $productId);
        }

        $nets = $movementQuery->get();

        if ($nets->isEmpty()) {
            return collect();
        }

        $productIds = $nets->pluck('product_id')->unique()->values();
        $posIds     = $nets->pluck('point_of_sale_id')->unique()->values();

        $thresholds = StockLevel::query()
            ->whereIn('product_id', $productIds)
            ->whereIn('point_of_sale_id', $posIds)
            ->get()
            ->keyBy(fn (StockLevel $l) => "{$l->product_id}|{$l->point_of_sale_id}");

        $products = Product::query()->whereIn('id', $productIds)->get()->keyBy('id');
        $posList  = PointOfSale::query()->whereIn('id', $posIds)->get()->keyBy('id');

        return $nets->map(function (object $row) use ($thresholds, $products, $posList): StockLevel {
            $key      = "{$row->product_id}|{$row->point_of_sale_id}";
            $existing = $thresholds->get($key);

            $level = new StockLevel([
                'product_id'       => $row->product_id,
                'point_of_sale_id' => $row->point_of_sale_id,
                'quantity'         => (int) $row->net_quantity,
                'minimum_quantity' => $existing?->minimum_quantity ?? 0,
            ]);
            $level->id = $existing?->id ?? "{$row->product_id}:{$row->point_of_sale_id}";
            $level->setRelation('product', $products->get($row->product_id));
            $level->setRelation('pointOfSale', $posList->get($row->point_of_sale_id));

            return $level;
        })->values();
    }
}
