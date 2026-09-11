<?php

declare(strict_types=1);

/**
 * Audit log tests — Task 0.5: Transversal foundation
 *
 * These tests are RED before the Coder's work.
 * The classes App\Platform\Audit\Providers\AuditServiceProvider, the Auditable trait,
 * the `audits` migration and config/audit.php do not exist yet.
 *
 * Source of truth: socle-prompt/etat/conception-0.5.md
 *
 * Decisions:
 *   D1 — Migration published from vendor, moved into database/migrations/tenant/
 *   D2 — User resolver via callback Audit::resolveUserIdUsing()
 *   D3 — Spatie tables (roles, permissions, pivots) excluded from audit
 */

use App\Control\Tenant;
use App\Platform\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

// ─────────────────────────────────────────────────────────────────────────────
// T1 — Audit configured with the current User UUID
// ─────────────────────────────────────────────────────────────────────────────

it('it_configures_audit_with_user_uuid', function (): void {
    // Arrange — create and initialize a tenant
    $tenant = Tenant::create([
        'name'   => 'Audit Corp',
        'status' => 'actif',
    ]);

    tenancy()->initialize($tenant);

    // Create a User in the tenant context
    $user = User::create([
        'first_name' => 'Auditor',
        'last_name'  => 'Dupont',
        'phone'      => '+237600000101',
        'password'   => bcrypt('secret'),
    ]);

    // Simulate authentication of this user
    Auth::login($user);

    // Act — modify a user field (triggers the Auditable trait)
    $user->first_name = 'ModifiedAuditor';
    $user->save();

    // Assert — at least one row was recorded in the audits table
    expect(DB::table('audits')->count())->toBeGreaterThan(0);

    // Assert — the 'updated' event (fired after Auth::login) carries the user UUID.
    // The 'created' event fired before Auth::login so its user_id is null — expected.
    $updatedAudit = DB::table('audits')->where('event', 'updated')->first();
    expect($updatedAudit)->not->toBeNull();
    expect($updatedAudit->user_id)->toBe((string) $user->id);

    tenancy()->end();
});

// ─────────────────────────────────────────────────────────────────────────────
// T2 — Spatie tables (roles, permissions) are excluded from audit
// ─────────────────────────────────────────────────────────────────────────────

it('it_excludes_spatie_tables_from_audit', function (): void {
    // Arrange — create and initialize a tenant
    $tenant = Tenant::create([
        'name'   => 'Spatie Exclusion Inc',
        'status' => 'actif',
    ]);

    tenancy()->initialize($tenant);

    // Act — create a Spatie role then rename it (operations that would trigger
    // an audit if Role/Permission were not excluded)
    $role = \Spatie\Permission\Models\Role::create([
        'name'       => 'manager',
        'guard_name' => 'web',
    ]);

    $role->name = 'supervisor';
    $role->save();

    // Assert — no rows in audits for Spatie types
    expect(
        DB::table('audits')
            ->where('auditable_type', 'LIKE', '%Permission%')
            ->count()
    )->toBe(0);

    expect(
        DB::table('audits')
            ->where('auditable_type', 'LIKE', '%Role%')
            ->count()
    )->toBe(0);

    tenancy()->end();
});
