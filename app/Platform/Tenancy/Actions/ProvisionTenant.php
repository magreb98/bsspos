<?php

declare(strict_types=1);

namespace App\Platform\Tenancy\Actions;

use App\Control\Tenant;
use App\Platform\Identity\Models\Perimeter;
use App\Platform\Identity\Models\User;
use App\Platform\Identity\Seeders\RolesAndPermissionsSeeder;
use App\Platform\Tenancy\Enums\ProvisioningStep;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\PermissionRegistrar;

class ProvisionTenant
{
    public function execute(Tenant $tenant): void
    {
        // Capture initial_admin before any save() that could modify object state.
        // VirtualColumn exposes JSON data either via data[] (before save) or
        // via getAttribute() (after decode post-save).
        $initialAdmin = ($tenant->data['initial_admin'] ?? null)
            ?? $tenant->getAttribute('initial_admin');

        if ($tenant->provisioning_step < ProvisioningStep::DatabaseCreated->value) {
            $this->executeStep(
                $tenant,
                ProvisioningStep::DatabaseCreated->value,
                fn () => $this->createDatabase($tenant)
            );
        }

        if ($tenant->provisioning_step < ProvisioningStep::MigrationsApplied->value) {
            $this->executeStep(
                $tenant,
                ProvisioningStep::MigrationsApplied->value,
                fn () => $this->applyMigrations($tenant)
            );
        }

        if ($tenant->provisioning_step < ProvisioningStep::ReferenceDataLoaded->value) {
            $this->executeStep(
                $tenant,
                ProvisioningStep::ReferenceDataLoaded->value,
                fn () => $this->loadReferenceData($tenant)
            );
        }

        if (
            $tenant->provisioning_step < ProvisioningStep::AdminCreated->value
            && $initialAdmin !== null
        ) {
            $this->executeStep(
                $tenant,
                ProvisioningStep::AdminCreated->value,
                fn () => $this->createAdmin($tenant, $initialAdmin)
            );
        }

        if ($tenant->provisioning_step < ProvisioningStep::SubscriptionOpened->value) {
            $this->executeStep(
                $tenant,
                ProvisioningStep::SubscriptionOpened->value,
                fn () => $this->openSubscription($tenant)
            );
        }
    }

    protected function createDatabase(Tenant $tenant): void
    {
        if (! App::environment('testing')) {
            $tenant->database()->manager()->createDatabase($tenant);
        }
    }

    protected function applyMigrations(Tenant $tenant): void
    {
        Artisan::call('tenants:migrate', ['--tenants' => [$tenant->id], '--force' => true]);
    }

    protected function loadReferenceData(Tenant $tenant): void
    {
        $tenant->run(fn () => (new RolesAndPermissionsSeeder())->run());
    }

    /** @param array<string, mixed> $adminData */
    protected function createAdmin(Tenant $tenant, array $adminData): void
    {
        $tenant->run(function () use ($tenant, $adminData): void {
            // Flush the Spatie cache: it is shared across tenants (same cache key).
            // Without this, roles loaded from a previously-provisioned tenant could
            // be returned here, causing "role not found" on a fresh tenant DB.
            app(PermissionRegistrar::class)->forgetCachedPermissions();

            DB::transaction(function () use ($tenant, $adminData): void {
                $phone = (string) ($adminData['phone'] ?? '');

                // Idempotent: skip if admin already exists from a prior partial run.
                $user = User::where('phone', $phone)->first();

                if ($user === null) {
                    $user = User::create([
                        'first_name' => (string) ($adminData['first_name'] ?? ''),
                        'last_name'  => (string) ($adminData['last_name'] ?? ''),
                        'phone'      => $phone,
                        'password'   => Hash::make((string) ($adminData['password'] ?? '')),
                    ]);
                }

                if (! $user->hasRole('proprietaire')) {
                    $user->assignRole('proprietaire');
                }

                if ($user->perimeters()->doesntExist()) {
                    $perimeter = Perimeter::firstOrCreate(
                        ['name' => $tenant->name, 'type' => 'root', 'parent_id' => null]
                    );
                    $user->perimeters()->attach($perimeter->id);
                }
            });
        });
    }

    protected function openSubscription(Tenant $tenant): void
    {
        // TODO: create the tenant's initial subscription (billing, plan assignment, etc.)
    }

    /**
     * Runs a single provisioning step under a row lock on the tenant, so that
     * two concurrently-dispatched jobs for the same tenant (e.g. a duplicate
     * dispatch racing a retry) cannot both read the same provisioning_step,
     * both decide the step still needs to run, and both execute it.
     *
     * The lock is held for the whole read-check-execute-write cycle: we open
     * the transaction ourselves (rather than via DB::transaction()) so that on
     * failure we can COMMIT the `provisioning_error` write instead of losing it
     * to an automatic rollback, while still holding the tenant row lock until
     * the step is fully resolved (success or failure).
     */
    private function executeStep(Tenant $tenant, int $step, \Closure $fn): void
    {
        $connection = $tenant->getConnection();

        $connection->beginTransaction();

        try {
            /** @var Tenant|null $locked */
            $locked = $tenant->newQuery()->whereKey($tenant->getKey())->lockForUpdate()->first();

            if ($locked !== null && $locked->provisioning_step >= $step) {
                // Another process already completed this step while we were
                // waiting for the lock (or before we got here) — skip it.
                $connection->commit();

                return;
            }

            $tenant->provisioning_error = null;
            $tenant->save();

            $fn();

            $tenant->provisioning_step = $step;
            $tenant->save();

            $connection->commit();
        } catch (\Throwable $e) {
            $tenant->provisioning_error = $e->getMessage();
            $tenant->save();

            $connection->commit();

            throw $e;
        }
    }
}
