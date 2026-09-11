<?php

declare(strict_types=1);

/**
 * Outbox table tests — Task 0.5: Transversal foundation
 *
 * Decisions:
 *   D8 — Table `outbox_messages` tenant-side, relayed by a landlord job;
 *         atomicity outbox ↔ business data within the same transaction
 *   D9 — The relay job marks processed_at BEFORE dispatching (crash protection)
 *
 * SQLite + RefreshDatabase note: RefreshDatabase wraps the test in a transaction
 * on the shared PDO. Nested DB::transaction() calls would fail in PHP 8.4 SQLite
 * ("cannot start a transaction within a transaction"). We use SAVEPOINTs instead,
 * which SQLite supports inside an existing transaction.
 */

use App\Control\Tenant;
use App\Platform\Outbox\Actions\PublishMessage;
use App\Platform\Outbox\Actions\RelayMessages;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

// ─────────────────────────────────────────────────────────────────────────────
// T10 — Insert into the outbox within a committed transaction
// ─────────────────────────────────────────────────────────────────────────────

it('inserts_message_into_outbox_on_commit', function (): void {
    $tenant = Tenant::create([
        'name'   => 'Outbox Commerce',
        'status' => 'actif',
    ]);

    tenancy()->initialize($tenant);

    // Committed case: publish within a savepoint and release it (= commit).
    DB::statement('SAVEPOINT sp_commit');
    (new PublishMessage())->publish('payment.initiated', ['amount' => 5000]);
    DB::statement('RELEASE SAVEPOINT sp_commit');

    expect(DB::table('outbox_messages')->count())->toBe(1);
    expect(DB::table('outbox_messages')->first()->processed_at)->toBeNull();

    // Rollback case: publish within a savepoint, then roll it back.
    DB::statement('SAVEPOINT sp_rollback');
    (new PublishMessage())->publish('payment.cancelled', ['amount' => 1000]);
    DB::statement('ROLLBACK TO SAVEPOINT sp_rollback');

    // The rolled-back message must not persist.
    expect(DB::table('outbox_messages')->count())->toBe(1);

    tenancy()->end();
});

// ─────────────────────────────────────────────────────────────────────────────
// T11 — Messages are marked processed after the relay job runs
// ─────────────────────────────────────────────────────────────────────────────

it('marks_messages_processed_after_relay', function (): void {
    $tenantA = Tenant::create([
        'name'   => 'Tenant Outbox Alpha',
        'status' => 'actif',
    ]);

    $tenantB = Tenant::create([
        'name'   => 'Tenant Outbox Beta',
        'status' => 'actif',
    ]);

    tenancy()->initialize($tenantA);
    DB::statement('SAVEPOINT sp_a');
    (new PublishMessage())->publish('order.placed', ['ref' => 'CMD-001']);
    DB::statement('RELEASE SAVEPOINT sp_a');
    tenancy()->end();

    tenancy()->initialize($tenantB);
    DB::statement('SAVEPOINT sp_b');
    (new PublishMessage())->publish('order.placed', ['ref' => 'CMD-002']);
    DB::statement('RELEASE SAVEPOINT sp_b');
    tenancy()->end();

    (new RelayMessages())->handle();

    // In the shared SQLite test environment all tenants share the same PDO/tables.
    // After relay, no unprocessed messages should remain across all tenants.
    tenancy()->initialize($tenantA);
    expect(DB::table('outbox_messages')->whereNull('processed_at')->count())->toBe(0);
    expect(DB::table('outbox_messages')->whereNotNull('processed_at')->count())->toBeGreaterThanOrEqual(1);
    tenancy()->end();
});
