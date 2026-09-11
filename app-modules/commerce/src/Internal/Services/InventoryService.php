<?php

declare(strict_types=1);

namespace Modules\Commerce\Internal\Services;

use Modules\Commerce\Internal\Models\InventoryCount;
use Modules\Commerce\Internal\Models\PointOfSale;
use Modules\Commerce\Internal\Models\StockMovement;

final class InventoryService
{
    /**
     * @param list<array{product_id: string, counted_quantity: int}> $lines
     */
    public function count(PointOfSale $pos, array $lines, ?string $notes = null): InventoryCount
    {
        $inventoryCount = InventoryCount::create([
            'point_of_sale_id' => $pos->id,
            'counted_at'       => now(),
            'notes'            => $notes,
        ]);

        foreach ($lines as $line) {
            $theoretical = (int) StockMovement::where('product_id', $line['product_id'])->sum('quantity');
            $adjustment  = $line['counted_quantity'] - $theoretical;

            $inventoryCount->lines()->create([
                'product_id'           => $line['product_id'],
                'theoretical_quantity' => $theoretical,
                'counted_quantity'     => $line['counted_quantity'],
                'adjustment'           => $adjustment,
            ]);

            if ($adjustment !== 0) {
                StockMovement::create([
                    'point_of_sale_id' => $pos->id,
                    'product_id'       => $line['product_id'],
                    'sale_line_id'     => null,
                    'quantity'         => $adjustment,
                    'occurred_at'      => now(),
                ]);
            }
        }

        return $inventoryCount;
    }
}
