<?php

declare(strict_types=1);

namespace Modules\Commerce\Internal\Metrics;

final class MetricDefinition
{
    public function __construct(
        public readonly string $name,
        public readonly string $permission,
        public readonly string $label,
    ) {
    }
}
