<?php

declare(strict_types=1);

/**
 * Provisioning step 4 tests — Task 0.4
 *
 * These tests are RED before the Coder's work.
 * The createAdmin() method is not yet in ProvisionTenant,
 * and the classes User / Perimeter do not exist yet.
 *
 * Source of truth: socle-prompt/etat/conception-0.4.md — Decisions D10, D11, D12
 *
 * Preconditions for T8:
 *   - provisioning_step = 3 (steps 1-3 already simulated)
 *   - The 'proprietaire' role is created MANUALLY in the tenant database
 *     (because step 3 loadReferenceData is empty in task 0.3;
 *      step 4 does not replay previous steps)
 *   - data['initial_admin'] contains first_name, last_name, phone
 *
 * Idempotency (T9):
 *   - Rule 5 CLAUDE.md: every write is idempotent.
 *   - If provisioning_step = 4, execute() does not recreate the admin.
 */

use App\Control\Tenant;
use App\Platform\Identity\Models\Perimeter;
use App\Platform\Identity\Models\User;
use App\Platform\Tenancy\Actions\ProvisionTenant;
use App\Platform\Tenancy\Enums\ProvisioningStep;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

// ─────────────────────────────────────────────────────────────────────────────
// T8 — Provisioning step 4: creation of the first administrator
// ─────────────────────────────────────────────────────────────────────────────

it('it_provisions_step_4_and_creates_the_first_administrator', function (): void {
    // Arrange — create a tenant with the first administrator's details
    $tenant = Tenant::create([
        'name'   => 'Dupont SARL',
        'status' => 'actif',
    ]);

    // Store initial_admin in the data column (jsonb via Stancl VirtualColumn)
    $tenant->data = array_merge($tenant->data ?? [], [
        'initial_admin' => [
            'first_name' => 'Jean',
            'last_name'  => 'Dupont',
            'phone'      => '+237600000001',
        ],
    ]);

    // Simulate that steps 1, 2, 3 have already been executed
    $tenant->provisioning_step = ProvisioningStep::ReferenceDataLoaded->value;
    $tenant->save();

    // Initialize the tenant context to create the 'proprietaire' role manually
    // (step 3 loadReferenceData is empty in task 0.3)
    tenancy()->initialize($tenant);
    \Spatie\Permission\Models\Role::create(['name' => 'proprietaire', 'guard_name' => 'web']);
    tenancy()->end();

    // Act — execute provisioning (must advance from 3 → 4)
    $action = new ProvisionTenant();
    $action->execute($tenant);

    // Assert 1 — step advanced to full completion (step 5); step 5 runs after admin creation
    expect($tenant->fresh()->provisioning_step)
        ->toBe(ProvisioningStep::SubscriptionOpened->value);

    // Assert 2 — exactly 1 User exists in the tenant database
    tenancy()->initialize($tenant);
    expect(DB::table('members')->count())->toBe(1);

    // Assert 3 — this user has the 'proprietaire' role
    $user = User::first();
    expect($user)->not->toBeNull();
    expect($user->hasRole('proprietaire'))->toBeTrue();

    // Assert 4 — exactly 1 root perimeter exists (parent_id IS NULL)
    expect(DB::table('perimeters')->whereNull('parent_id')->count())->toBe(1);

    tenancy()->end();
});

// ─────────────────────────────────────────────────────────────────────────────
// T9 — Idempotency: admin is not recreated if step 4 already done
// ─────────────────────────────────────────────────────────────────────────────

it('it_does_not_recreate_the_admin_if_step_4_already_done', function (): void {
    // Arrange — tenant whose step 4 has already been completed
    $tenant = Tenant::create([
        'name'   => 'Gamma SARL',
        'status' => 'actif',
    ]);

    $tenant->provisioning_step = ProvisioningStep::AdminCreated->value;
    $tenant->save();

    // Manually create a user in the tenant database (as if step 4 had run)
    tenancy()->initialize($tenant);

    \Spatie\Permission\Models\Role::create(['name' => 'proprietaire', 'guard_name' => 'web']);

    $existingUser = User::create([
        'first_name' => 'Alice',
        'last_name'  => 'Existing',
        'phone'      => '+237600000050',
        'password'   => bcrypt('secret'),
    ]);

    $existingUser->assignRole('proprietaire');

    tenancy()->end();

    // Act — re-run execute() on a tenant already at step 4
    $action = new ProvisionTenant();
    $action->execute($tenant);

    // Assert — still exactly 1 user (no duplicate)
    tenancy()->initialize($tenant);
    expect(DB::table('members')->count())->toBe(1);
    tenancy()->end();
});

// ─────────────────────────────────────────────────────────────────────────────
// T12 — Root perimeter creation during provisioning
// ─────────────────────────────────────────────────────────────────────────────

it('it_creates_a_root_perimeter_during_provisioning', function (): void {
    // Arrange — same setup as T8
    $tenant = Tenant::create([
        'name'   => 'Delta Commerce',
        'status' => 'actif',
    ]);

    $tenant->data = array_merge($tenant->data ?? [], [
        'initial_admin' => [
            'first_name' => 'Pierre',
            'last_name'  => 'Martin',
            'phone'      => '+237600000060',
        ],
    ]);

    $tenant->provisioning_step = ProvisioningStep::ReferenceDataLoaded->value;
    $tenant->save();

    // Prepare the role in the tenant context
    tenancy()->initialize($tenant);
    \Spatie\Permission\Models\Role::create(['name' => 'proprietaire', 'guard_name' => 'web']);
    tenancy()->end();

    // Act — provision step 4
    $action = new ProvisionTenant();
    $action->execute($tenant);

    // Assert — exactly 1 perimeter with parent_id IS NULL
    tenancy()->initialize($tenant);

    $rootPerimeter = Perimeter::whereNull('parent_id')->first();
    expect($rootPerimeter)->not->toBeNull();
    expect(Perimeter::whereNull('parent_id')->count())->toBe(1);

    // Assert — root perimeter name matches the tenant name (D11)
    expect($rootPerimeter->name)->toBe($tenant->name);

    tenancy()->end();
});
