<?php

declare(strict_types=1);

namespace Modules\Commerce\Contracts;

use Modules\Commerce\Internal\Models\SaleLine;

/**
 * Variation point 2/5 — sector-specific line validation before a sale is confirmed.
 *
 * For serial sectors:  verify the targeted unit is Available and not double-booked.
 * For batch sectors:   verify sufficient batch stock exists.
 * For quantity sectors: no extra validation needed (base stock check suffices).
 */
interface SaleLineValidationContract
{
    /**
     * Validate a sale line before the sale is confirmed.
     * $context carries sector-specific identifiers (e.g. serial_number, batch_id).
     *
     * @param array<string, mixed> $context
     * @throws \DomainException if the line cannot be validated
     */
    public function validate(SaleLine $line, array $context = []): void;
}
