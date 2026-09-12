<?php

declare(strict_types=1);

/**
 * Orchestration tests — Task 0.3: Artisan commands SocleMigrateTenants and SocleDetectDrift
 *
 * These tests are RED before the Coder's work (Artisan commands,
 * the tenant_schema_versions table and new columns do not exist yet).
 * They become GREEN once the Coder has completed task 0.3.
 *
 * Source of truth: socle-prompt/etat/conception-0.3.md
 *
 * Environment:
 *   - DB_CONNECTION=sqlite / DB_DATABASE=:memory: (phpunit.xml)
 *   - The landlord database runs on SQLite in-memory via RefreshDatabase.
 *
 * Conventions:
 *   - Each test covers only one behaviour.
 *   - No conditional logic (if/else) in test bodies.
 *   - Names in English describing intent, not implementation.
 */

use App\Console\Commands\SocleDetectDrift;
use App\Console\Commands\SocleMigrateTenants;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

// ─────────────────────────────────────────────────────────────────────────────
// T5 — successful migration of all active tenants
// ─────────────────────────────────────────────────────────────────────────────

test('it_migrates_all_active_tenants_and_updates_the_version', function (): void {
    // Arrange — two active tenants registered in the landlord database
    $tenantAlpha = \App\Control\Tenant::create([
        'name'   => 'Alpha SARL',
        'status' => 'actif',
    ]);

    $tenantBeta = \App\Control\Tenant::create([
        'name'   => 'Beta Corp',
        'status' => 'actif',
    ]);

    // Act — the command must iterate over both tenants and update the version table
    $this->artisan('socle:migrate-tenants')
        ->assertExitCode(0);

    // Assert — both tenant_ids are recorded in tenant_schema_versions
    $versions = DB::table('tenant_schema_versions')
        ->pluck('tenant_id')
        ->toArray();

    expect($versions)
        ->toContain($tenantAlpha->id)
        ->toContain($tenantBeta->id);
});

// ─────────────────────────────────────────────────────────────────────────────
// T6 — one failing tenant doesn't block the rest of the fleet
// ─────────────────────────────────────────────────────────────────────────────

test('it_continues_migrating_remaining_tenants_after_one_failure', function (): void {
    // Arrange — three active tenants; the second will cause a failure.
    $tenantFirst  = \App\Control\Tenant::create([
        'name'   => 'First SARL',
        'status' => 'actif',
    ]);

    $tenantSecond = \App\Control\Tenant::create([
        'name'   => 'Second Corp Failure',
        'status' => 'actif',
    ]);

    $tenantThird  = \App\Control\Tenant::create([
        'name'   => 'Third Inc',
        'status' => 'actif',
    ]);

    // Anonymous subclass that overrides migrateTenant() to simulate
    // a failure on the second tenant only.
    $failureId = $tenantSecond->id;
    app()->bind(SocleMigrateTenants::class, static function () use ($failureId): SocleMigrateTenants {
        return new class ($failureId) extends SocleMigrateTenants {
            public function __construct(private readonly string $tenantIdFailure)
            {
                parent::__construct();
            }

            protected function migrateTenant(\App\Control\Tenant $tenant): void
            {
                if ($tenant->id === $this->tenantIdFailure) {
                    throw new \RuntimeException('Simulated second tenant migration failure');
                }

                parent::migrateTenant($tenant);
            }
        };
    });

    // Act — the command must still report failure (exit code 1) since one
    // tenant failed, but it must not let that failure block the rest of the
    // fleet from being migrated.
    $this->artisan('socle:migrate-tenants')
        ->assertExitCode(1);

    // Assert — first AND third are recorded; only the second (which threw)
    // is missing from tenant_schema_versions.
    $versions = DB::table('tenant_schema_versions')
        ->pluck('tenant_id')
        ->toArray();

    expect($versions)
        ->toContain($tenantFirst->id)
        ->toContain($tenantThird->id)
        ->not->toContain($tenantSecond->id);
});

// ─────────────────────────────────────────────────────────────────────────────
// T7 — detection of drifted tenants (old version)
// ─────────────────────────────────────────────────────────────────────────────

test('it_detects_drifted_tenants', function (): void {
    // Arrange — a tenant registered with an old version in the tracking table
    $tenant = \App\Control\Tenant::create([
        'name'   => 'Lagging Corp',
        'status' => 'actif',
    ]);

    // Insert an obsolete version (batch 0 while current version is at least batch_1)
    DB::table('tenant_schema_versions')->insert([
        'tenant_id'  => $tenant->id,
        'version'    => 'batch_0',
        'updated_at' => now()->toIso8601String(),
    ]);

    // Act — the command must detect that this tenant is behind
    $result = $this->artisan('socle:detect-drift');

    // Assert — exit code 1 means at least one tenant is drifted
    $result->assertExitCode(1);
});

// ─────────────────────────────────────────────────────────────────────────────
// T8 — no drift detected → exit code 0
// ─────────────────────────────────────────────────────────────────────────────

test('it_returns_exit_code_0_if_no_drift', function (): void {
    // Arrange — a tenant at the current version (max batch from the migrations table)
    $tenant = \App\Control\Tenant::create([
        'name'   => 'UpToDate Inc',
        'status' => 'actif',
    ]);

    // Retrieve the real current version (max batch from the landlord migrations table)
    $batchMax = DB::table('migrations')->max('batch') ?? 1;
    $currentVersion = 'batch_' . $batchMax;

    // Insert the current version in tenant_schema_versions
    DB::table('tenant_schema_versions')->insert([
        'tenant_id'  => $tenant->id,
        'version'    => $currentVersion,
        'updated_at' => now()->toIso8601String(),
    ]);

    // Act — the command must detect no gap
    $result = $this->artisan('socle:detect-drift');

    // Assert — exit code 0 means all tenants are up to date
    $result->assertExitCode(0);
});
