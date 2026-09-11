<?php

declare(strict_types=1);

namespace App\Platform\Identity\Bootstrappers;

use Spatie\Permission\PermissionRegistrar;
use Stancl\Tenancy\Contracts\TenancyBootstrapper;
use Stancl\Tenancy\Contracts\Tenant;

final class PrefixPermissionsCache implements TenancyBootstrapper
{
    public function __construct(private readonly PermissionRegistrar $registrar)
    {
    }

    public function bootstrap(Tenant $tenant): void
    {
        $this->registrar->cacheKey = 'spatie.permission.cache.' . $tenant->getTenantKey();
        $this->registrar->forgetCachedPermissions();
    }

    public function revert(): void
    {
        $this->registrar->cacheKey = 'spatie.permission.cache';
        $this->registrar->forgetCachedPermissions();
    }
}
