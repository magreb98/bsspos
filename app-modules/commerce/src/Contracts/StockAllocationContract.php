<?php

declare(strict_types=1);

namespace Modules\Commerce\Contracts;

use Modules\Commerce\Internal\Models\SaleLine;

/**
 * Variation point 1/5 — how a sector allocates and releases stock for a sale line.
 *
 * For quantity-based sectors: decrement/restore stock_levels.
 * For serial sectors:         assign/release a named serial unit.
 * For batch sectors:          consume/restore from the nearest-expiry batch.
 */
interface StockAllocationContract
{
    /**
     * Allocate stock when a sale line is confirmed.
     * $context carries sector-specific identifiers (e.g. serial_number, batch_id).
     *
     * @param array<string, mixed> $context
     * @throws \DomainException if allocation is not possible
     */
    public function allocate(SaleLine $line, array $context = []): void;

    /**
     * Release stock back when a sale line is returned or cancelled.
     *
     * @throws \DomainException if the line has no active allocation
     */
    public function release(SaleLine $line): void;
}
