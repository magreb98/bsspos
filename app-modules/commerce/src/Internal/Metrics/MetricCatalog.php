<?php

declare(strict_types=1);

namespace Modules\Commerce\Internal\Metrics;

use App\Platform\Identity\Models\User;

final class MetricCatalog
{
    /** @var array<string, MetricDefinition> */
    private array $definitions = [];

    public function register(MetricDefinition $definition): void
    {
        $this->definitions[$definition->name] = $definition;
    }

    public function get(string $name): MetricDefinition
    {
        return $this->definitions[$name] ?? throw new \DomainException("Unknown metric: {$name}");
    }

    /**
     * Returns metrics the given user has permission to see.
     *
     * @return list<MetricDefinition>
     */
    public function visibleFor(User $user): array
    {
        return array_values(array_filter(
            $this->definitions,
            fn (MetricDefinition $def) => $user->hasPermissionTo($def->permission),
        ));
    }
}
