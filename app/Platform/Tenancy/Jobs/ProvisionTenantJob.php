<?php

declare(strict_types=1);

namespace App\Platform\Tenancy\Jobs;

use App\Control\Tenant;
use App\Platform\Tenancy\Actions\ProvisionTenant;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

final class ProvisionTenantJob implements ShouldQueue, ShouldBeUnique
{
    use Queueable;

    /**
     * Provisioning is a multi-step, resumable process; a stuck/slow job should
     * not lock out retries forever, but it should never legitimately run
     * longer than this.
     */
    public int $uniqueFor = 3600;

    /** @var int */
    public $tries = 3;

    public function __construct(
        private readonly Tenant $tenant,
    ) {
        $this->onConnection('database');
    }

    /**
     * Ensures only one provisioning job runs at a time for a given tenant,
     * preventing two concurrently-dispatched jobs from racing through the
     * same provisioning steps.
     */
    public function uniqueId(): string
    {
        return (string) $this->tenant->id;
    }

    /** @return array<int, int> */
    public function backoff(): array
    {
        return [30, 120, 300];
    }

    public function handle(ProvisionTenant $action): void
    {
        $action->execute($this->tenant);
    }
}
