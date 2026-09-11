<?php

declare(strict_types=1);

namespace Modules\Commerce\Internal\Sync;

final readonly class OfflineSaleRequest
{
    /**
     * @param list<array{product_id: string, quantity: int, designation: string,
     *                   unit_price: int, vat_rate: string,
     *                   line_total_excluding_tax: int, line_total_tax: int,
     *                   line_total_including_tax: int}> $lines
     */
    public function __construct(
        public string $idempotencyKey,
        public string $cashSessionId,
        public array $lines,
    ) {
    }
}
