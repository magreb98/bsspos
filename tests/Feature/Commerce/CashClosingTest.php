<?php

declare(strict_types=1);

/**
 * Feature tests for expenses and cash closing — Task 4.3
 *
 * Decisions:
 *   D1 — expenses table
 *   D2 — cash_closings table (broken down by payment method)
 *   D3 — CashClosingService computes expected_cash = opening + cash_sales - expenses
 *   D4 — Discrepancy recorded; session still closed regardless
 */

use App\Control\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Commerce\Internal\Enums\PaymentMethod;
use Modules\Commerce\Internal\Enums\PaymentStatus;
use Modules\Commerce\Internal\Enums\SaleState;
use Modules\Commerce\Internal\Enums\SessionState;
use Modules\Commerce\Internal\Models\CashRegister;
use Modules\Commerce\Internal\Models\CashSession;
use Modules\Commerce\Internal\Models\Expense;
use Modules\Commerce\Internal\Models\OrganizationalUnit;
use Modules\Commerce\Internal\Models\Payment;
use Modules\Commerce\Internal\Models\PointOfSale;
use Modules\Commerce\Internal\Models\Sale;
use Modules\Commerce\Internal\Services\CashClosingService;
use Ramsey\Uuid\Uuid;

uses(RefreshDatabase::class);

// ─────────────────────────────────────────────────────────────────────────────
// Helper
// ─────────────────────────────────────────────────────────────────────────────

function makeClosingSession(int $openingBalance = 0): CashSession
{
    $unit     = OrganizationalUnit::create(['name' => 'Closing HQ', 'active' => true]);
    $pos      = PointOfSale::create(['name' => 'Closing POS', 'organizational_unit_id' => $unit->id, 'active' => true]);
    $register = CashRegister::create(['name' => 'Closing R1', 'point_of_sale_id' => $pos->id, 'active' => true]);

    return CashSession::create([
        'cash_register_id' => $register->id,
        'state'            => SessionState::Open,
        'opened_at'        => now(),
        'opening_balance'  => $openingBalance,
        'opened_by'        => Uuid::uuid7()->toString(),
    ]);
}

function addConfirmedSale(CashSession $session, PaymentMethod $method, int $amount): void
{
    $sale = Sale::create([
        'cash_session_id' => $session->id,
        'state'           => SaleState::Confirmed,
        'idempotency_key' => Uuid::uuid7()->toString(),
        'total_including_tax' => $amount,
    ]);

    Payment::create([
        'sale_id'      => $sale->id,
        'method'       => $method,
        'amount'       => $amount,
        'status'       => PaymentStatus::Confirmed,
        'confirmed_at' => now(),
    ]);
}

// ─────────────────────────────────────────────────────────────────────────────
// T1 — Cash sales and expenses: expected_cash is computed correctly
// ─────────────────────────────────────────────────────────────────────────────

it('closing_computes_expected_cash_from_opening_cash_sales_and_expenses', function (): void {
    $tenant = Tenant::create(['name' => 'Closing Corp A', 'status' => 'actif']);
    tenancy()->initialize($tenant);

    $session = makeClosingSession(openingBalance: 50_000);

    addConfirmedSale($session, PaymentMethod::Cash, 30_000);
    addConfirmedSale($session, PaymentMethod::Cash, 20_000);

    Expense::create([
        'cash_session_id' => $session->id,
        'amount'          => 5_000,
        'label'           => 'Transport',
        'recorded_at'     => now(),
    ]);

    $closedBy = Uuid::uuid7()->toString();
    // expected = 50 000 + 30 000 + 20 000 - 5 000 = 95 000
    $closing = (new CashClosingService())->close($session, 95_000, $closedBy);

    expect($closing->opening_balance?->toInt())->toBe(50_000);
    expect($closing->cash_sales?->toInt())->toBe(50_000);
    expect($closing->expenses?->toInt())->toBe(5_000);
    expect($closing->expected_cash?->toInt())->toBe(95_000);
    expect($closing->declared_cash?->toInt())->toBe(95_000);
    expect($closing->discrepancy?->toInt())->toBe(0);
    $session->refresh();
    expect($session->state)->toBe(SessionState::Closed);

    tenancy()->end();
});

// ─────────────────────────────────────────────────────────────────────────────
// T2 — Mobile money sales appear in mobile_money_sales, not in expected_cash
// ─────────────────────────────────────────────────────────────────────────────

it('mobile_money_sales_are_separated_from_cash_in_closing', function (): void {
    $tenant = Tenant::create(['name' => 'Closing Corp B', 'status' => 'actif']);
    tenancy()->initialize($tenant);

    $session = makeClosingSession(openingBalance: 10_000);

    addConfirmedSale($session, PaymentMethod::Cash, 25_000);
    addConfirmedSale($session, PaymentMethod::MobileMoney, 40_000);

    $closedBy = Uuid::uuid7()->toString();
    // expected_cash = 10 000 + 25 000 - 0 = 35 000 (mobile money excluded)
    $closing = (new CashClosingService())->close($session, 35_000, $closedBy);

    expect($closing->cash_sales?->toInt())->toBe(25_000);
    expect($closing->mobile_money_sales?->toInt())->toBe(40_000);
    expect($closing->expected_cash?->toInt())->toBe(35_000);
    expect($closing->discrepancy?->toInt())->toBe(0);

    tenancy()->end();
});

// ─────────────────────────────────────────────────────────────────────────────
// T3 — Discrepancy is recorded; session still closed
// ─────────────────────────────────────────────────────────────────────────────

it('closing_discrepancy_is_recorded_and_session_is_still_closed', function (): void {
    $tenant = Tenant::create(['name' => 'Closing Corp C', 'status' => 'actif']);
    tenancy()->initialize($tenant);

    $session = makeClosingSession(openingBalance: 20_000);
    addConfirmedSale($session, PaymentMethod::Cash, 10_000);

    $closedBy = Uuid::uuid7()->toString();
    // expected = 30 000, declared = 28 000 → discrepancy = -2 000
    $closing = (new CashClosingService())->close($session, 28_000, $closedBy);

    expect($closing->expected_cash?->toInt())->toBe(30_000);
    expect($closing->declared_cash?->toInt())->toBe(28_000);
    expect($closing->discrepancy?->toInt())->toBe(-2_000);
    $session->refresh();
    expect($session->state)->toBe(SessionState::Closed);

    tenancy()->end();
});

// ─────────────────────────────────────────────────────────────────────────────
// T4 — No expenses, no sales: expected_cash = opening_balance
// ─────────────────────────────────────────────────────────────────────────────

it('closing_with_no_sales_no_expenses_expected_cash_equals_opening', function (): void {
    $tenant = Tenant::create(['name' => 'Closing Corp D', 'status' => 'actif']);
    tenancy()->initialize($tenant);

    $session  = makeClosingSession(openingBalance: 15_000);
    $closedBy = Uuid::uuid7()->toString();

    $closing = (new CashClosingService())->close($session, 15_000, $closedBy);

    expect($closing->cash_sales?->toInt())->toBe(0);
    expect($closing->mobile_money_sales?->toInt())->toBe(0);
    expect($closing->expenses?->toInt())->toBe(0);
    expect($closing->expected_cash?->toInt())->toBe(15_000);
    expect($closing->discrepancy?->toInt())->toBe(0);

    tenancy()->end();
});
