<?php

declare(strict_types=1);

/**
 * Feature tests for customer record — Task 1.3
 *
 * Decisions:
 *   D1 — customers: uuid PK, name, phone (unique), outstanding_balance bigint, credit_limit bigint, active
 *   D2 — AmountCast on balance fields; exceedsLimit() when balance > limit
 */

use App\Control\Tenant;
use App\Platform\Money\Amount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Modules\Commerce\Internal\Models\Customer;

uses(RefreshDatabase::class);

// ─────────────────────────────────────────────────────────────────────────────
// T1 — customers table has required columns
// ─────────────────────────────────────────────────────────────────────────────

it('customers_table_has_required_columns', function (): void {
    $tenant = Tenant::create(['name' => 'Customer Corp', 'status' => 'actif']);
    tenancy()->initialize($tenant);

    expect(Schema::hasTable('customers'))->toBeTrue();
    expect(Schema::hasColumns('customers', [
        'id', 'name', 'phone', 'outstanding_balance', 'credit_limit', 'active',
    ]))->toBeTrue();

    tenancy()->end();
});

// ─────────────────────────────────────────────────────────────────────────────
// T2 — outstanding_balance returns an Amount
// ─────────────────────────────────────────────────────────────────────────────

it('outstanding_balance_returns_amount', function (): void {
    $tenant = Tenant::create(['name' => 'Balance Corp', 'status' => 'actif']);
    tenancy()->initialize($tenant);

    $customer = Customer::create([
        'name'                => 'Jean Fotso',
        'phone'               => '+237600000001',
        'outstanding_balance' => 50000,
    ]);

    $fresh = Customer::findOrFail($customer->id);

    expect($fresh->outstanding_balance)->toBeInstanceOf(Amount::class);
    expect($fresh->outstanding_balance?->toInt())->toBe(50000);

    tenancy()->end();
});

// ─────────────────────────────────────────────────────────────────────────────
// T3 — credit_limit returns an Amount
// ─────────────────────────────────────────────────────────────────────────────

it('credit_limit_returns_amount', function (): void {
    $tenant = Tenant::create(['name' => 'Limit Corp', 'status' => 'actif']);
    tenancy()->initialize($tenant);

    $customer = Customer::create([
        'name'         => 'Marie Mbeki',
        'phone'        => '+237600000002',
        'credit_limit' => 200000,
    ]);

    $fresh = Customer::findOrFail($customer->id);

    expect($fresh->credit_limit)->toBeInstanceOf(Amount::class);
    expect($fresh->credit_limit?->toInt())->toBe(200000);

    tenancy()->end();
});

// ─────────────────────────────────────────────────────────────────────────────
// T4 — exceedsLimit() returns true when balance exceeds limit
// ─────────────────────────────────────────────────────────────────────────────

it('exceeds_limit_returns_true_when_balance_over_limit', function (): void {
    $tenant = Tenant::create(['name' => 'Limit Check Corp', 'status' => 'actif']);
    tenancy()->initialize($tenant);

    $over = Customer::create([
        'name'                => 'Paul Nkeng',
        'phone'               => '+237600000003',
        'outstanding_balance' => 150000,
        'credit_limit'        => 100000,
    ]);

    $under = Customer::create([
        'name'                => 'Alice Biya',
        'phone'               => '+237600000004',
        'outstanding_balance' => 40000,
        'credit_limit'        => 100000,
    ]);

    expect($over->exceedsLimit())->toBeTrue();
    expect($under->exceedsLimit())->toBeFalse();

    tenancy()->end();
});

// ─────────────────────────────────────────────────────────────────────────────
// T5 — deactivated customer remains readable
// ─────────────────────────────────────────────────────────────────────────────

it('deactivated_customer_remains_readable', function (): void {
    $tenant = Tenant::create(['name' => 'History Client Corp', 'status' => 'actif']);
    tenancy()->initialize($tenant);

    $customer = Customer::create([
        'name'   => 'Ancien client',
        'phone'  => '+237600000005',
        'active' => false,
    ]);

    $found = Customer::findOrFail($customer->id);

    expect($found->active)->toBeFalse();

    tenancy()->end();
});

// ─────────────────────────────────────────────────────────────────────────────
// T6 — phone uniqueness constraint
// ─────────────────────────────────────────────────────────────────────────────

it('phone_uniqueness_is_enforced', function (): void {
    $tenant = Tenant::create(['name' => 'Unique Phone Corp', 'status' => 'actif']);
    tenancy()->initialize($tenant);

    Customer::create(['name' => 'Client A', 'phone' => '+237600000006']);

    expect(
        fn () => Customer::create(['name' => 'Client B', 'phone' => '+237600000006'])
    )->toThrow(\Illuminate\Database\QueryException::class);

    tenancy()->end();
});
