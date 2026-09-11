<?php

declare(strict_types=1);

namespace App\Platform\Tenancy\Jobs;

use App\Control\Tenant;
use App\Platform\Tenancy\Actions\ProvisionTenant;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

final class ProvisionTenantJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly Tenant $tenant,
    ) {
        $this->onConnection('database');
    }

    public function handle(ProvisionTenant $action): void
    {
        $action->execute($this->tenant);
    }
}
