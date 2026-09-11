<?php

declare(strict_types=1);

namespace Modules\SecteurElectronique\Http\Data;

use Spatie\LaravelData\Data;

final class StoreSerialUnitData extends Data
{
    public function __construct(
        public readonly string $product_id,
        public readonly string $serial_number,
    ) {
    }
}
