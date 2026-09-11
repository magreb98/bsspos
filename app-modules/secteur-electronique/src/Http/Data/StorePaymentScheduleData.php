<?php

declare(strict_types=1);

namespace Modules\SecteurElectronique\Http\Data;

use Spatie\LaravelData\Data;

final class StorePaymentScheduleData extends Data
{
    /**
     * @param array<int, array{amount: int, due_on: string}> $installments
     */
    public function __construct(
        public readonly string $sale_id,
        public readonly int $deposit,
        public readonly array $installments,
    ) {
    }
}
