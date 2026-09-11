<?php

declare(strict_types=1);

namespace Modules\Commerce\Internal\Services;

/**
 * @phpstan-type PosSummary array{pos_id: string, pos_name: string, sale_count: int, total_including_tax: int}
 */
final class DashboardResult
{
    /**
     * @param list<PosSummary> $byPos
     */
    public function __construct(
        public readonly int $totalSaleCount,
        public readonly int $totalExcludingTax,
        public readonly int $totalTax,
        public readonly int $totalIncludingTax,
        public readonly bool $isProvisional,
        public readonly array $byPos,
    ) {
    }

    public static function empty(): self
    {
        return new self(0, 0, 0, 0, false, []);
    }
}
