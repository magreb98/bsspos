<?php

declare(strict_types=1);

namespace App\Platform\Mcp;

use App\Platform\Identity\Models\User;

interface MetricListingContract
{
    /**
     * Returns descriptors for metrics the given user is allowed to see.
     *
     * Each descriptor is an associative array with at least 'name' and 'label'.
     *
     * @return list<array{name: string, label: string}>
     */
    public function visibleDescriptorsFor(User $user): array;
}
