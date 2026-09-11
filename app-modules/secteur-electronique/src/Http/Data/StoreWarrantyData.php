<?php

declare(strict_types=1);

namespace Modules\SecteurElectronique\Http\Data;

use Spatie\LaravelData\Data;

final class StoreWarrantyData extends Data
{
    public function __construct(
        public readonly string $serial_unit_id,
        public readonly string $sale_line_id,
        public readonly int $duration_months,
    ) {
    }
}
