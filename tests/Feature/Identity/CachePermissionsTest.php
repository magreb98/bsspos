<?php

declare(strict_types=1);

/**
 * Permission cache prefix tests — Task 0.4
 *
 * These tests are RED before the Coder's work.
 * The class App\Platform\Identity\Bootstrappers\PrefixPermissionsCache
 * does not exist yet.
 *
 * Source of truth: socle-prompt/etat/conception-0.4.md — Decisions D6, D7
 *
 * SQLite architecture in tests:
 *   SwitchTenantConnection shares the same PDO between landlord and tenant.
 *   Tables are shared between tenants. What T3 proves is that the
 *   Spatie CACHE KEY changes from one tenant to another — not that data
 *   in the database is physically isolated (PostgreSQL property, outside test scope).
 *
 * T3 is the LOT 0 ACCEPTANCE CRITERION: negative test proving a tenant
 * never inherits another tenant's permissions.
 *
 * T4 and T5 are unit tests: they do not need RefreshDatabase.
 */

use App\Control\Tenant;
use App\Platform\Identity\Bootstrappers\PrefixPermissionsCache;
use App\Platform\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;

// RefreshDatabase only for T3 (which creates tenants and users)
// T4 and T5 are declared without uses(RefreshDatabase::class) — see inline annotations
uses(RefreshDatabase::class);

// ─────────────────────────────────────────────────────────────────────────────
// T3 — LOT 0 CRITERION: permission cache isolation between two tenants
// ─────────────────────────────────────────────────────────────────────────────

it('it_denies_access_to_another_tenants_permissions', function (): void {
    // Arrange — two distinct tenants
    $tenantA = Tenant::create(['name' => 'Tenant Alpha', 'status' => 'actif']);
    $tenantB = Tenant::create(['name' => 'Tenant Beta', 'status' => 'actif']);

    // ── Context tenantA ─────────────────────────────────────────────────────
    tenancy()->initialize($tenantA);

    // Create the 'proprietaire' role and a user in tenantA
    \Spatie\Permission\Models\Role::create(['name' => 'proprietaire', 'guard_name' => 'web']);

    $userA = User::create([
        'first_name' => 'Jean',
        'last_name'  => 'Alpha',
        'phone'      => '+237600000010',
        'password'   => bcrypt('secret'),
    ]);

    $userA->assignRole('proprietaire');

    // Force Spatie cache loading (effective hasRole call)
    $userA->hasRole('proprietaire');

    // Assert — cache key contains tenantA's UUID (D7)
    $keyA = app(PermissionRegistrar::class)->cacheKey;
    expect($keyA)->toBe('spatie.permission.cache.' . $tenantA->id);

    tenancy()->end();

    // ── Context tenantB ─────────────────────────────────────────────────────
    tenancy()->initialize($tenantB);

    // Assert — cache key now contains tenantB's UUID (NOT tenantA)
    $keyB = app(PermissionRegistrar::class)->cacheKey;
    expect($keyB)->toBe('spatie.permission.cache.' . $tenantB->id);
    expect($keyB)->not->toBe($keyA);

    // Create a User in tenantB WITHOUT assigning a role
    $userB = User::create([
        'first_name' => 'Marie',
        'last_name'  => 'Beta',
        'phone'      => '+237600000011',
        'password'   => bcrypt('secret'),
    ]);

    // Act + Assert — this user does NOT have the 'proprietaire' role in tenantB
    // (even if in shared SQLite the tables are common, the cache is isolated per tenant)
    expect($userB->hasRole('proprietaire'))->toBeFalse();

    tenancy()->end();
});

// ─────────────────────────────────────────────────────────────────────────────
// T4 — Unit: cache key prefixed with tenant UUID
// Note: unit test — no RefreshDatabase applied at this level
// ─────────────────────────────────────────────────────────────────────────────

it('it_prefixes_the_cache_key_with_tenant_uuid', function (): void {
    // Arrange — direct instantiation of the bootstrapper (IoC injection)
    $bootstrapper = new PrefixPermissionsCache(app(PermissionRegistrar::class));

    // Create a dummy tenant (the Tenant model must exist)
    // Note: this is a unit test but we need a Tenant object for getTenantKey()
    $tenant = Tenant::create([
        'name'   => 'Unit Test Cache',
        'status' => 'actif',
    ]);

    // Act — call bootstrap() with this tenant
    $bootstrapper->bootstrap($tenant);

    // Assert — the cache key is prefixed with the tenant's UUID
    $expectedKey = 'spatie.permission.cache.' . $tenant->id;
    expect(app(PermissionRegistrar::class)->cacheKey)->toBe($expectedKey);
})->uses(RefreshDatabase::class);

// ─────────────────────────────────────────────────────────────────────────────
// T5 — Unit: cache key reset to default after revert()
// ─────────────────────────────────────────────────────────────────────────────

it('it_resets_the_cache_key_to_default_after_revert', function (): void {
    // Arrange — direct instantiation of the bootstrapper
    $bootstrapper = new PrefixPermissionsCache(app(PermissionRegistrar::class));

    // Prepare a state with a prefixed key (simulate a previous bootstrap)
    app(PermissionRegistrar::class)->cacheKey = 'spatie.permission.cache.fake-uuid-1234';

    // Act — call revert()
    $bootstrapper->revert();

    // Assert — the key is reset to the default value (no tenant suffix)
    expect(app(PermissionRegistrar::class)->cacheKey)->toBe('spatie.permission.cache');
});
