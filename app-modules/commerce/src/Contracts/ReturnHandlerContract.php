<?php

declare(strict_types=1);

namespace Modules\Commerce\Contracts;

use Modules\Commerce\Internal\Models\SaleLine;

/**
 * Variation point 4/5 — sector-specific cleanup when a sale line is returned.
 *
 * For serial sectors:  release the serial unit back to Available, void its warranty.
 * For batch sectors:   restore consumed batch quantity.
 * For quantity sectors: restore stock level (handled by StockAllocationContract::release).
 */
interface ReturnHandlerContract
{
    /**
     * Handle the return of a confirmed sale line.
     * Called after the base stock release has already been applied.
     *
     * @throws \DomainException if the sector-specific return cannot be processed
     */
    public function onLineReturned(SaleLine $line): void;
}
