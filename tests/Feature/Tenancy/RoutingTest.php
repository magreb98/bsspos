<?php

declare(strict_types=1);

/**
 * Tenant routing tests — Task 0.2: Control base and tenant routing
 *
 * These tests are RED before the Coder's work (classes, middlewares,
 * migrations and tenant routes do not exist yet).
 * They become GREEN once the Coder has completed task 0.2.
 *
 * Source of truth: socle-prompt/etat/conception-0.2.md
 *
 * Environment:
 *   - DB_CONNECTION=sqlite / DB_DATABASE=:memory: (phpunit.xml)
 *   - The landlord database runs on SQLite in-memory via RefreshDatabase.
 *   - No real PostgreSQL database is created. Connection isolation is
 *     verified by the active connection name, not a real pgsql database.
 *
 * Conventions:
 *   - Each test covers only one behaviour.
 *   - No conditional logic (if/else) in test bodies.
 *   - Names in English describing intent, not implementation.
 *   - ReflectionClass assertions raise a fatal error if the class
 *     does not exist → guaranteed RED test without application code.
 */

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

// ─────────────────────────────────────────────────────────────────────────────
// NOMINAL — correct tenant resolution from domain
// ─────────────────────────────────────────────────────────────────────────────

test('it_resolves_the_tenant_from_the_subdomain', function (): void {
    // Arrange — a tenant registered with its bsspos.cm subdomain
    $tenant = \App\Control\Tenant::create([
        'name'   => 'Acme SARL',
        'status' => 'actif',
    ]);

    \App\Control\Domaine::create([
        'tenant_id' => $tenant->id,
        'domaine'   => 'acme.bsspos.cm',
    ]);

    // Act — incoming request with the correct Host header
    $this->withHeaders(['Host' => 'acme.bsspos.cm'])->get('/');

    // Assert — the current tenant is indeed acme's
    expect(\Stancl\Tenancy\Facades\Tenancy::tenant())->not->toBeNull()
        ->and(\Stancl\Tenancy\Facades\Tenancy::tenant()->id)->toBe($tenant->id);
});

test('it_resolves_the_tenant_from_a_custom_domain', function (): void {
    // Arrange — a tenant registered with a personal domain (no bsspos subdomain)
    $tenant = \App\Control\Tenant::create([
        'name'   => 'My Shop',
        'status' => 'actif',
    ]);

    \App\Control\Domaine::create([
        'tenant_id' => $tenant->id,
        'domaine'   => 'myshop.cm',
    ]);

    // Act — request on the custom domain
    $this->withHeaders(['Host' => 'myshop.cm'])->get('/');

    // Assert — custom domain resolved with priority
    expect(\Stancl\Tenancy\Facades\Tenancy::tenant())->not->toBeNull()
        ->and(\Stancl\Tenancy\Facades\Tenancy::tenant()->id)->toBe($tenant->id);
});

test('the_active_connection_carries_the_resolved_tenant_database_name', function (): void {
    // Arrange
    $tenant = \App\Control\Tenant::create([
        'name'   => 'Acme SARL',
        'status' => 'actif',
    ]);

    \App\Control\Domaine::create([
        'tenant_id' => $tenant->id,
        'domaine'   => 'acme.bsspos.cm',
    ]);

    // Act — direct tenant initialization to test connection switch
    // without depending on HTTP routing
    tenancy()->initialize($tenant);

    $activeDatabaseName = DB::connection()->getDatabaseName();

    // Assert — naming convention bsspos_tenant_{uuid}
    expect($activeDatabaseName)->toBe('bsspos_tenant_' . $tenant->id);
});

// ─────────────────────────────────────────────────────────────────────────────
// EXCEPTIONS — rejections and protections
// ─────────────────────────────────────────────────────────────────────────────

test('it_rejects_an_unknown_domain_with_404', function (): void {
    // Arrange — no tenant registered for this domain

    // Act — request on an unknown domain
    $response = $this->withHeaders(['Host' => 'unknown.bsspos.cm'])->get('/');

    // Assert — 404, no tenant resolved
    $response->assertStatus(404);
    expect(\Stancl\Tenancy\Facades\Tenancy::tenant())->toBeNull();
});

test('it_forbids_landlord_access_from_a_tenant_context', function (): void {
    // Arrange — an active tenant with its domain
    $tenant = \App\Control\Tenant::create([
        'name'   => 'Acme SARL',
        'status' => 'actif',
    ]);

    \App\Control\Domaine::create([
        'tenant_id' => $tenant->id,
        'domaine'   => 'acme.bsspos.cm',
    ]);

    // Act — request from a tenant context to a landlord route
    // protected by PreventAccessFromTenant
    $response = $this->withHeaders(['Host' => 'acme.bsspos.cm'])->get('/control');

    // Assert — middleware returns 403 (tenant context on landlord route is forbidden)
    $response->assertStatus(403);
});

test('it_does_not_let_a_tenant_reach_another_tenants_database', function (): void {
    // Arrange — two distinct tenants
    $tenantAcme = \App\Control\Tenant::create([
        'name'   => 'Acme SARL',
        'status' => 'actif',
    ]);

    $tenantBeta = \App\Control\Tenant::create([
        'name'   => 'Beta Corp',
        'status' => 'actif',
    ]);

    \App\Control\Domaine::create([
        'tenant_id' => $tenantAcme->id,
        'domaine'   => 'acme.bsspos.cm',
    ]);

    \App\Control\Domaine::create([
        'tenant_id' => $tenantBeta->id,
        'domaine'   => 'beta.bsspos.cm',
    ]);

    // Act — initialize acme tenant only
    tenancy()->initialize($tenantAcme);

    $activeDatabaseName = DB::connection()->getDatabaseName();

    // Assert — connection only carries acme's database name, not beta's
    expect($activeDatabaseName)->toBe('bsspos_tenant_' . $tenantAcme->id)
        ->and($activeDatabaseName)->not->toBe('bsspos_tenant_' . $tenantBeta->id);
});

// ─────────────────────────────────────────────────────────────────────────────
// STRUCTURAL INVARIANTS — files required by the design
// ─────────────────────────────────────────────────────────────────────────────

test('the_tenancy_service_provider_exists', function (): void {
    expect(base_path('app/Platform/Tenancy/TenancyServiceProvider.php'))->toBeFile();
});

test('the_initialise_tenant_middleware_exists', function (): void {
    expect(base_path('app/Platform/Tenancy/Middleware/InitialiseTenant.php'))->toBeFile();
});

test('the_prevent_access_from_tenant_middleware_exists', function (): void {
    expect(base_path('app/Platform/Tenancy/Middleware/PreventAccessFromTenant.php'))->toBeFile();
});

test('the_tenant_model_exists_in_app_control', function (): void {
    expect(base_path('app/Control/Tenant.php'))->toBeFile();
});

test('the_domaine_model_exists_in_app_control', function (): void {
    expect(base_path('app/Control/Domaine.php'))->toBeFile();
});

test('the_tenants_table_migration_exists', function (): void {
    $files = glob(base_path('database/migrations/*create_tenants_table.php'));

    expect($files)->not->toBeEmpty();
});

test('the_domains_table_migration_exists', function (): void {
    $byDomaines = glob(base_path('database/migrations/*create_domaines_table.php'));
    $byDomains  = glob(base_path('database/migrations/*create_domains_table.php'));

    expect(array_merge($byDomaines, $byDomains))->not->toBeEmpty();
});

// ─────────────────────────────────────────────────────────────────────────────
// STRUCTURAL INVARIANTS — tenants table schema
// ─────────────────────────────────────────────────────────────────────────────

test('the_tenants_table_exists_in_database', function (): void {
    $columns = array_column(
        DB::select("PRAGMA table_info('tenants')"),
        'name',
    );

    expect($columns)->not->toBeEmpty();
});

test('the_tenants_table_contains_the_required_columns', function (): void {
    $columns = array_column(
        DB::select("PRAGMA table_info('tenants')"),
        'name',
    );

    expect($columns)
        ->toContain('id')
        ->toContain('data');
});

test('the_tenants_table_does_not_contain_a_tenant_id_column', function (): void {
    $columns = array_column(
        DB::select("PRAGMA table_info('tenants')"),
        'name',
    );

    expect($columns)->not->toBeEmpty('The tenants table must exist to test this invariant')
        ->and($columns)->not->toContain('tenant_id');
});

test('the_domains_table_contains_the_structural_fk_to_tenants', function (): void {
    $columnsDomains  = array_column(DB::select("PRAGMA table_info('domains')"), 'name');
    $columnsDomaines = array_column(DB::select("PRAGMA table_info('domaines')"), 'name');

    $columns = $columnsDomains ?: $columnsDomaines;

    expect($columns)->not->toBeEmpty('The domains/domaines table must exist')
        ->and($columns)->toContain('tenant_id');
});

test('the_domains_table_does_not_contain_business_data', function (): void {
    $columnsDomains  = array_column(DB::select("PRAGMA table_info('domains')"), 'name');
    $columnsDomaines = array_column(DB::select("PRAGMA table_info('domaines')"), 'name');

    $columns = $columnsDomains ?: $columnsDomaines;

    expect($columns)->not->toBeEmpty('The domains/domaines table must exist')
        ->and($columns)->not->toContain('produit_id')
        ->and($columns)->not->toContain('vente_id')
        ->and($columns)->not->toContain('montant');
});

// ─────────────────────────────────────────────────────────────────────────────
// STRUCTURAL INVARIANTS — class hierarchy (PHP reflection)
// ─────────────────────────────────────────────────────────────────────────────

test('the_tenant_model_extends_the_stancl_model', function (): void {
    $reflection = new ReflectionClass(\App\Control\Tenant::class);

    expect($reflection->getParentClass()->getName())
        ->toBe(\Stancl\Tenancy\Database\Models\Tenant::class);
});

test('the_domaine_model_extends_the_stancl_model', function (): void {
    $reflection = new ReflectionClass(\App\Control\Domaine::class);

    expect($reflection->getParentClass()->getName())
        ->toBe(\Stancl\Tenancy\Database\Models\Domain::class);
});

test('the_tenant_model_is_a_final_class', function (): void {
    $reflection = new ReflectionClass(\App\Control\Tenant::class);

    expect($reflection->isFinal())->toBeTrue();
});

test('the_domaine_model_is_a_final_class', function (): void {
    $reflection = new ReflectionClass(\App\Control\Domaine::class);

    expect($reflection->isFinal())->toBeTrue();
});

test('the_tenant_model_has_the_est_actif_method', function (): void {
    $reflection = new ReflectionClass(\App\Control\Tenant::class);

    expect($reflection->hasMethod('estActif'))->toBeTrue();
});

test('the_est_actif_method_returns_a_boolean', function (): void {
    $reflection  = new ReflectionClass(\App\Control\Tenant::class);
    $method      = $reflection->getMethod('estActif');
    $returnType  = $method->getReturnType();

    expect($returnType)->not->toBeNull()
        ->and((string) $returnType)->toBe('bool');
});

test('the_tenant_model_has_the_domaines_relation', function (): void {
    $reflection = new ReflectionClass(\App\Control\Tenant::class);

    expect($reflection->hasMethod('domaines'))->toBeTrue();
});

test('the_domaine_model_has_the_tenant_relation', function (): void {
    $reflection = new ReflectionClass(\App\Control\Domaine::class);

    expect($reflection->hasMethod('tenant'))->toBeTrue();
});

test('the_tenancy_service_provider_is_a_final_class', function (): void {
    $reflection = new ReflectionClass(\App\Platform\Tenancy\TenancyServiceProvider::class);

    expect($reflection->isFinal())->toBeTrue();
});

test('the_initialise_tenant_middleware_is_a_final_class', function (): void {
    $reflection = new ReflectionClass(\App\Platform\Tenancy\Middleware\InitialiseTenant::class);

    expect($reflection->isFinal())->toBeTrue();
});

test('the_prevent_access_from_tenant_middleware_is_a_final_class', function (): void {
    $reflection = new ReflectionClass(\App\Platform\Tenancy\Middleware\PreventAccessFromTenant::class);

    expect($reflection->isFinal())->toBeTrue();
});

// ─────────────────────────────────────────────────────────────────────────────
// INVARIANT RULE 1 — Platform/Tenancy contains no business references
// ─────────────────────────────────────────────────────────────────────────────

test('platform_tenancy_contains_no_reference_to_a_business_model', function (): void {
    // Rule 1 — The platform knows no business domain (CLAUDE.md)
    $platformPath = base_path('app/Platform/Tenancy');

    // The folder must exist — otherwise the test is RED for the right reason
    expect($platformPath)->toBeDirectory();

    $businessTerms = ['produit', 'vente', 'salaire', 'vehicule', 'stock', 'client'];

    $iterator = new \RecursiveIteratorIterator(
        new \RecursiveDirectoryIterator($platformPath, \FilesystemIterator::SKIP_DOTS),
    );

    $violations = [];

    foreach ($iterator as $file) {
        if ($file->getExtension() !== 'php') {
            continue;
        }

        $content = strtolower((string) file_get_contents($file->getPathname()));

        foreach ($businessTerms as $term) {
            if (str_contains($content, $term)) {
                $violations[] = "{$file->getFilename()} contains '{$term}'";
            }
        }
    }

    expect($violations)->toBeEmpty(
        'Rule 1 violation in app/Platform/Tenancy/: ' . implode(', ', $violations)
    );
});
