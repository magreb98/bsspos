<?php

declare(strict_types=1);

namespace Modules\Commerce\Internal\Sync;

final readonly class OfflineSyncResult
{
    public function __construct(
        public int $processed,
        public int $replayed,
        public int $anomalies,
        public int $failed = 0,
    ) {
    }
}
