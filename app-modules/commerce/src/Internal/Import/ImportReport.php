<?php

declare(strict_types=1);

namespace Modules\Commerce\Internal\Import;

final readonly class ImportReport
{
    /**
     * @param list<ImportError> $errors
     */
    public function __construct(
        public int $imported,
        public int $skipped,
        public array $errors,
    ) {
    }

    public function hasErrors(): bool
    {
        return $this->errors !== [];
    }
}
