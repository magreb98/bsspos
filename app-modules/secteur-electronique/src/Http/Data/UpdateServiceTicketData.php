<?php

declare(strict_types=1);

namespace Modules\SecteurElectronique\Http\Data;

use Spatie\LaravelData\Data;
use Spatie\LaravelData\Optional;

final class UpdateServiceTicketData extends Data
{
    public function __construct(
        public readonly string|Optional $status,
        public readonly string|Optional $description,
    ) {
    }
}
