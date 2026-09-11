<?php

declare(strict_types=1);

use App\Platform\Tenancy\TenancyServiceProvider;
use App\Providers\AppServiceProvider;

return [
    AppServiceProvider::class,
    TenancyServiceProvider::class,
    \App\Platform\Identity\Providers\IdentityServiceProvider::class,
    \App\Platform\Audit\Providers\AuditServiceProvider::class,
    \App\Platform\Registry\Providers\RegistryServiceProvider::class,
];
