<?php

declare(strict_types=1);

/**
 * Identity tests — Task 0.4: Tenant user, landlord directory
 *
 * These tests are RED before the Coder's work.
 * The class App\Platform\Identity\Models\User and the corresponding
 * migrations do not exist yet.
 *
 * Source of truth: socle-prompt/etat/conception-0.4.md
 *
 * SQLite architecture in tests:
 *   SwitchTenantConnection shares the same PDO between landlord and tenant.
 *   All tables live in the same in-memory database.
 *   Isolating a User in the "tenant context" means it is in the
 *   `members` table, never in `users` (landlord scaffold table).
 */

use App\Control\Tenant;
use App\Platform\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

// ─────────────────────────────────────────────────────────────────────────────
// T1 — Create a User in the tenant database
// ─────────────────────────────────────────────────────────────────────────────

it('it_creates_a_user_in_the_tenant_database', function (): void {
    // Arrange — create and initialize a tenant
    $tenant = Tenant::create([
        'name'   => 'Acme SARL',
        'status' => 'actif',
    ]);

    tenancy()->initialize($tenant);

    // Act — create a User in the tenant context
    User::create([
        'first_name' => 'Jean',
        'last_name'  => 'Dupont',
        'phone'      => '+237600000001',
        'password'   => bcrypt('secret'),
    ]);

    // Assert — the user exists in the `members` table (tenant)
    expect(DB::table('members')->count())->toBe(1);

    // Assert — the user does NOT exist in the `users` table (landlord scaffold)
    expect(DB::table('users')->count())->toBe(0);

    tenancy()->end();
});

// ─────────────────────────────────────────────────────────────────────────────
// T2 — Assign the 'proprietaire' role to a User
// ─────────────────────────────────────────────────────────────────────────────

it('it_assigns_the_proprietaire_role_to_a_user', function (): void {
    // Arrange — tenant context with the Spatie role created
    $tenant = Tenant::create([
        'name'   => 'Beta Corp',
        'status' => 'actif',
    ]);

    tenancy()->initialize($tenant);

    // Create the 'proprietaire' role via Spatie (in tenant context)
    \Spatie\Permission\Models\Role::create(['name' => 'proprietaire', 'guard_name' => 'web']);

    // Create a User and assign the role
    $user = User::create([
        'first_name' => 'Marie',
        'last_name'  => 'Curie',
        'phone'      => '+237600000002',
        'password'   => bcrypt('secret'),
    ]);

    // Act
    $user->assignRole('proprietaire');

    // Assert — the user has the 'proprietaire' role
    expect($user->hasRole('proprietaire'))->toBeTrue();

    tenancy()->end();
});

// ─────────────────────────────────────────────────────────────────────────────
// T10 — Landlord directory: no secret, no permission
// ─────────────────────────────────────────────────────────────────────────────

it('it_inserts_into_identity_directory_without_secret_or_permission', function (): void {
    // Arrange — outside tenant context, create a tenant to have a valid UUID
    $tenant = Tenant::create([
        'name'   => 'Gamma Inc',
        'status' => 'actif',
    ]);

    // Act — insert into identity_directory (landlord table)
    DB::table('identity_directory')->insert([
        'tenant_id'       => $tenant->id,
        'identifier_type' => 'phone',
        'identifier'      => '+237600000099',
    ]);

    // Assert — the row was inserted
    expect(DB::table('identity_directory')->count())->toBe(1);

    // Assert — security/permission columns are ABSENT from this table
    expect(Schema::hasColumn('identity_directory', 'password'))->toBeFalse();
    expect(Schema::hasColumn('identity_directory', 'role'))->toBeFalse();
    expect(Schema::hasColumn('identity_directory', 'permissions'))->toBeFalse();

    // Assert — expected columns are present
    expect(Schema::hasColumn('identity_directory', 'tenant_id'))->toBeTrue();
    expect(Schema::hasColumn('identity_directory', 'identifier_type'))->toBeTrue();
    expect(Schema::hasColumn('identity_directory', 'identifier'))->toBeTrue();
});
