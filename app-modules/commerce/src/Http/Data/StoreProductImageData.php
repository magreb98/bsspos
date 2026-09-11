<?php

declare(strict_types=1);

namespace Modules\Commerce\Http\Data;

use Spatie\LaravelData\Data;
use Spatie\LaravelData\Optional;

final class StoreProductImageData extends Data
{
    public function __construct(
        public readonly string $url,
        public readonly int|Optional $position,
    ) {
    }
}
