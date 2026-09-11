<?php

declare(strict_types=1);

/**
 * Feature tests for cash session lifecycle — Task 2.1
 *
 * Decisions:
 *   D1 — cash_sessions table with required columns
 *   D2 — SessionState enum: Open, Closed
 *   D3 — CashSession model: isOpen(), open(), close()
 *   D4 — INV-09: closed session refuses further sales (enforced in task 2.2)
 */

use App\Control\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Commerce\Internal\Enums\SessionState;
use Modules\Commerce\Internal\Models\CashRegister;
use Modules\Commerce\Internal\Models\CashSession;
use Modules\Commerce\Internal\Models\OrganizationalUnit;
use Modules\Commerce\Internal\Models\PointOfSale;
use Ramsey\Uuid\Uuid;

uses(RefreshDatabase::class);

// ─────────────────────────────────────────────────────────────────────────────
// Helpers
// ─────────────────────────────────────────────────────────────────────────────

function makeCashRegister(): CashRegister
{
    $unit = OrganizationalUnit::create(['name' => 'HQ', 'active' => true]);
    $pos  = PointOfSale::create(['name' => 'Main POS', 'organizational_unit_id' => $unit->id, 'active' => true]);

    return CashRegister::create(['name' => 'Register 1', 'point_of_sale_id' => $pos->id, 'active' => true]);
}

// ─────────────────────────────────────────────────────────────────────────────
// T1 — cash_sessions table has required columns
// ─────────────────────────────────────────────────────────────────────────────

it('cash_sessions_table_has_required_columns', function (): void {
    $tenant = Tenant::create(['name' => 'Session Corp A', 'status' => 'actif']);
    tenancy()->initialize($tenant);

    $columns = \Illuminate\Support\Facades\Schema::getColumnListing('cash_sessions');

    expect($columns)->toContain('id');
    expect($columns)->toContain('cash_register_id');
    expect($columns)->toContain('state');
    expect($columns)->toContain('opened_at');
    expect($columns)->toContain('closed_at');
    expect($columns)->toContain('opening_balance');
    expect($columns)->toContain('closing_balance');
    expect($columns)->toContain('opened_by');
    expect($columns)->toContain('closed_by');

    tenancy()->end();
});

// ─────────────────────────────────────────────────────────────────────────────
// T2 — opening a session creates a record with state=open and opened_at set
// ─────────────────────────────────────────────────────────────────────────────

it('opening_a_session_sets_state_and_opened_at', function (): void {
    $tenant = Tenant::create(['name' => 'Session Corp B', 'status' => 'actif']);
    tenancy()->initialize($tenant);

    $register = makeCashRegister();
    $userId   = Uuid::uuid7()->toString();

    $session = CashSession::create([
        'cash_register_id' => $register->id,
        'state'            => SessionState::Open,
        'opened_at'        => now(),
        'opening_balance'  => 10000,
        'opened_by'        => $userId,
    ]);

    expect($session->state)->toBe(SessionState::Open);
    expect($session->opened_at)->not->toBeNull();
    expect($session->closed_at)->toBeNull();
    expect($session->isOpen())->toBeTrue();

    tenancy()->end();
});

// ─────────────────────────────────────────────────────────────────────────────
// T3 — closing a session updates state=closed and closed_at
// ─────────────────────────────────────────────────────────────────────────────

it('closing_a_session_sets_state_and_closed_at', function (): void {
    $tenant = Tenant::create(['name' => 'Session Corp C', 'status' => 'actif']);
    tenancy()->initialize($tenant);

    $register = makeCashRegister();
    $userId   = Uuid::uuid7()->toString();

    $session = CashSession::create([
        'cash_register_id' => $register->id,
        'state'            => SessionState::Open,
        'opened_at'        => now(),
        'opening_balance'  => 10000,
        'opened_by'        => $userId,
    ]);

    $session->close($userId, 25000);
    $session->refresh();

    expect($session->state)->toBe(SessionState::Closed);
    expect($session->closed_at)->not->toBeNull();
    expect($session->closing_balance?->toInt())->toBe(25000);
    expect($session->isOpen())->toBeFalse();

    tenancy()->end();
});

// ─────────────────────────────────────────────────────────────────────────────
// T4 — isOpen() returns true when open, false when closed
// ─────────────────────────────────────────────────────────────────────────────

it('is_open_returns_correct_boolean_by_state', function (): void {
    $tenant = Tenant::create(['name' => 'Session Corp D', 'status' => 'actif']);
    tenancy()->initialize($tenant);

    $register = makeCashRegister();
    $userId   = Uuid::uuid7()->toString();

    $open = CashSession::create([
        'cash_register_id' => $register->id,
        'state'            => SessionState::Open,
        'opened_at'        => now(),
        'opening_balance'  => 0,
        'opened_by'        => $userId,
    ]);

    $closed = CashSession::create([
        'cash_register_id' => $register->id,
        'state'            => SessionState::Closed,
        'opened_at'        => now()->subHour(),
        'closed_at'        => now(),
        'opening_balance'  => 0,
        'closing_balance'  => 0,
        'opened_by'        => $userId,
        'closed_by'        => $userId,
    ]);

    expect($open->isOpen())->toBeTrue();
    expect($closed->isOpen())->toBeFalse();

    tenancy()->end();
});

// ─────────────────────────────────────────────────────────────────────────────
// T5 — closing an already-closed session throws a domain exception
// ─────────────────────────────────────────────────────────────────────────────

it('closing_already_closed_session_throws', function (): void {
    $tenant = Tenant::create(['name' => 'Session Corp E', 'status' => 'actif']);
    tenancy()->initialize($tenant);

    $register = makeCashRegister();
    $userId   = Uuid::uuid7()->toString();

    $session = CashSession::create([
        'cash_register_id' => $register->id,
        'state'            => SessionState::Closed,
        'opened_at'        => now()->subHour(),
        'closed_at'        => now(),
        'opening_balance'  => 0,
        'closing_balance'  => 0,
        'opened_by'        => $userId,
        'closed_by'        => $userId,
    ]);

    expect(fn () => $session->close($userId, 0))
        ->toThrow(\DomainException::class);

    tenancy()->end();
});

// ─────────────────────────────────────────────────────────────────────────────
// T6 — opening_balance and closing_balance return Amount objects
// ─────────────────────────────────────────────────────────────────────────────

it('balances_return_amount_objects', function (): void {
    $tenant = Tenant::create(['name' => 'Session Corp F', 'status' => 'actif']);
    tenancy()->initialize($tenant);

    $register = makeCashRegister();
    $userId   = Uuid::uuid7()->toString();

    $session = CashSession::create([
        'cash_register_id' => $register->id,
        'state'            => SessionState::Open,
        'opened_at'        => now(),
        'opening_balance'  => 50000,
        'opened_by'        => $userId,
    ]);

    $session->refresh();

    expect($session->opening_balance)->toBeInstanceOf(\App\Platform\Money\Amount::class);
    expect($session->opening_balance?->toInt())->toBe(50000);
    expect($session->closing_balance)->toBeNull();

    tenancy()->end();
});
