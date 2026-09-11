<?php

declare(strict_types=1);

namespace Modules\Commerce\Internal\Import;

final readonly class ImportError
{
    public function __construct(
        public int $line,
        public string $reference,
        public string $reason,
    ) {
    }
}
