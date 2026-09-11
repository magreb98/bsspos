<?php

declare(strict_types=1);

namespace Modules\Commerce\Internal\Documents;

use DateTimeInterface;

final readonly class InvoiceData
{
    /**
     * @param list<InvoiceLineData> $lines
     */
    public function __construct(
        public string $invoiceNumber,
        public string $niu,
        public string $rccm,
        public string $companyName,
        public int $totalExcludingTax,
        public int $totalTax,
        public int $totalIncludingTax,
        public string $vatRate,
        public array $lines,
        public DateTimeInterface $confirmedAt,
    ) {
    }
}
