<?php

declare(strict_types=1);

namespace Modules\Commerce\Internal\Services;

use Modules\Commerce\Internal\Enums\Granularity;
use Modules\Commerce\Internal\Enums\TransferStatus;
use Modules\Commerce\Internal\Models\StockMovement;
use Modules\Commerce\Internal\Models\Transfer;

final class TransferService
{
    public function dispatch(Transfer $transfer): void
    {
        if ($transfer->status !== TransferStatus::Pending) {
            throw new \DomainException('Only pending transfers can be dispatched.');
        }

        $lines  = $transfer->lines()->with('product')->get();
        $source = $transfer->sourcePos ?? throw new \DomainException('Transfer has no source POS.');

        foreach ($lines as $line) {
            if ($line->product === null || $line->product->granularity !== Granularity::Quantity) {
                continue;
            }

            StockMovement::create([
                'point_of_sale_id' => $source->id,
                'product_id'       => $line->product_id,
                'sale_line_id'     => null,
                'quantity'         => -$line->quantity,
                'occurred_at'      => now(),
            ]);
        }

        $transfer->update([
            'status'        => TransferStatus::InTransit,
            'dispatched_at' => now(),
        ]);
    }

    public function receive(Transfer $transfer): void
    {
        if ($transfer->status !== TransferStatus::InTransit) {
            throw new \DomainException('Only in-transit transfers can be received.');
        }

        $lines       = $transfer->lines()->with('product')->get();
        $destination = $transfer->destinationPos ?? throw new \DomainException('Transfer has no destination POS.');

        foreach ($lines as $line) {
            if ($line->product === null || $line->product->granularity !== Granularity::Quantity) {
                continue;
            }

            StockMovement::create([
                'point_of_sale_id' => $destination->id,
                'product_id'       => $line->product_id,
                'sale_line_id'     => null,
                'quantity'         => $line->quantity,
                'occurred_at'      => now(),
            ]);
        }

        $transfer->update([
            'status'      => TransferStatus::Received,
            'received_at' => now(),
        ]);
    }
}
