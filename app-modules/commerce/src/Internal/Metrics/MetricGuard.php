<?php

declare(strict_types=1);

namespace Modules\Commerce\Internal\Metrics;

use App\Platform\Identity\Models\User;

final class MetricGuard
{
    public function __construct(private readonly MetricCatalog $catalog)
    {
    }

    /**
     * Returns the MetricDefinition if the user holds the required permission.
     *
     * @throws \DomainException if metric is unknown or user lacks permission
     */
    public function authorize(string $name, User $user): MetricDefinition
    {
        $definition = $this->catalog->get($name);

        if (! $user->hasPermissionTo($definition->permission)) {
            throw new \DomainException(
                "Permission '{$definition->permission}' required to access metric '{$name}'."
            );
        }

        return $definition;
    }
}
