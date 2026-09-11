<?php

declare(strict_types=1);

namespace Modules\Commerce\Internal\Services;

use Modules\Commerce\Internal\Enums\Granularity;
use Modules\Commerce\Internal\Enums\SupplierOrderStatus;
use Modules\Commerce\Internal\Models\PointOfSale;
use Modules\Commerce\Internal\Models\Reception;
use Modules\Commerce\Internal\Models\StockMovement;
use Modules\Commerce\Internal\Models\SupplierOrder;

final class ReceptionService
{
    /**
     * @param list<array{product_id: string, quantity_expected: int, quantity_received: int, unit_cost: int}> $lines
     */
    public function receive(SupplierOrder $order, PointOfSale $pos, array $lines): Reception
    {
        $reception = Reception::create([
            'supplier_order_id' => $order->id,
            'point_of_sale_id'  => $pos->id,
            'received_at'       => now(),
        ]);

        foreach ($lines as $line) {
            $receptionLine = $reception->lines()->create([
                'product_id'        => $line['product_id'],
                'quantity_expected' => $line['quantity_expected'],
                'quantity_received' => $line['quantity_received'],
                'unit_cost'         => $line['unit_cost'],
            ]);

            $product = $receptionLine->product;

            if ($product !== null && $product->granularity === Granularity::Quantity && $line['quantity_received'] > 0) {
                StockMovement::create([
                    'point_of_sale_id' => $pos->id,
                    'product_id'       => $line['product_id'],
                    'sale_line_id'     => null,
                    'quantity'         => $line['quantity_received'],
                    'occurred_at'      => now(),
                ]);
            }
        }

        $order->update(['status' => SupplierOrderStatus::Received]);

        return $reception;
    }
}
