<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Control\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SocleMigrateTenants extends Command
{
    protected $signature = 'socle:migrate-tenants {--tenant=* : Targeted UUID(s), all if absent}';

    protected $description = 'Applies pending migrations on all active tenants';

    public function handle(): int
    {
        /** @var array<int,string> $uuids */
        $uuids = $this->option('tenant');

        /** @var \Illuminate\Support\Collection<int, Tenant> $tenants */
        $tenants = ! empty($uuids)
            ? Tenant::query()->whereIn('id', $uuids)->orderBy('created_at')->get()
            : Tenant::query()->where('status', 'actif')->orderBy('created_at')->get();

        $failures = [];

        foreach ($tenants as $tenant) {
            try {
                $this->migrateTenant($tenant);
                DB::table('tenant_schema_versions')->updateOrInsert(
                    ['tenant_id' => $tenant->id],
                    [
                        'version'    => 'batch_' . ((int) DB::table('migrations')->max('batch')),
                        'updated_at' => now(),
                    ]
                );
                $this->line("Tenant {$tenant->id} migrated.");
            } catch (\Throwable $e) {
                // Don't let one bad tenant block migrations for the rest of
                // the fleet — record the failure and keep going.
                $this->error("Failed tenant {$tenant->id}: {$e->getMessage()}");
                $failures[] = $tenant->id;
            }
        }

        if ($failures !== []) {
            $this->error(sprintf('%d tenant(s) failed to migrate: %s', count($failures), implode(', ', $failures)));

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    protected function migrateTenant(Tenant $tenant): void
    {
        $this->call('tenants:migrate', ['--tenants' => [$tenant->id], '--force' => true]);
    }
}
