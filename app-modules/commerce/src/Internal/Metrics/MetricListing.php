<?php

declare(strict_types=1);

namespace Modules\Commerce\Internal\Metrics;

use App\Platform\Identity\Models\User;
use App\Platform\Mcp\MetricListingContract;

final class MetricListing implements MetricListingContract
{
    public function __construct(private readonly MetricCatalog $catalog)
    {
    }

    /**
     * @return list<array{name: string, label: string}>
     */
    public function visibleDescriptorsFor(User $user): array
    {
        $definitions = $this->catalog->visibleFor($user);

        return array_map(
            fn (MetricDefinition $def) => ['name' => $def->name, 'label' => $def->label],
            $definitions,
        );
    }
}
