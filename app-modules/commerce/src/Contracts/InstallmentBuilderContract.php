<?php

declare(strict_types=1);

namespace Modules\Commerce\Contracts;

use Modules\Commerce\Internal\Models\Sale;

/**
 * Variation point 5/5 — building a deferred-payment schedule for a sale.
 *
 * Only meaningful for sectors that support credit sales (e.g. Electronic).
 * Enforces INV-06: deposit + sum(installments) must equal sale total exactly.
 */
interface InstallmentBuilderContract
{
    /**
     * Create a payment schedule for the sale.
     *
     * @param list<array{amount: int, due_on: string}> $installmentData
     * @throws \DomainException if INV-06 is violated (amounts do not sum to total)
     */
    public function build(Sale $sale, int $deposit, array $installmentData): void;
}
