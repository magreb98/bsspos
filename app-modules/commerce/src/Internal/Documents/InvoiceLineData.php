<?php

declare(strict_types=1);

namespace Modules\Commerce\Internal\Documents;

final readonly class InvoiceLineData
{
    public function __construct(
        public string $designation,
        public int $unitPrice,
        public int $quantity,
        public int $lineTotal,
    ) {
    }
}
