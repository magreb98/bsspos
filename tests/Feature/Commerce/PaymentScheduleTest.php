<?php

declare(strict_types=1);

/**
 * Feature tests for payment schedule — Task 7.4
 *
 * Decisions:
 *   D1 — PaymentSchedule: one per sale, holds deposit + total
 *   D2 — Installment: amount + due_on + paid_at
 *   D3 — PaymentScheduleService::create() enforces INV-06 at creation
 *   D4 — verify() re-checks INV-06 from DB sums
 */

use App\Control\Tenant;
use App\Platform\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Commerce\Internal\Enums\SaleState;
use Modules\Commerce\Internal\Enums\SessionState;
use Modules\Commerce\Internal\Models\CashRegister;
use Modules\Commerce\Internal\Models\CashSession;
use Modules\Commerce\Internal\Models\OrganizationalUnit;
use Modules\Commerce\Internal\Models\PointOfSale;
use Modules\Commerce\Internal\Models\Sale;
use Modules\SecteurElectronique\Models\Installment;
use Modules\SecteurElectronique\Models\PaymentSchedule;
use Modules\SecteurElectronique\Services\PaymentScheduleService;

uses(RefreshDatabase::class);

// ─────────────────────────────────────────────────────────────────────────────
// Helpers
// ─────────────────────────────────────────────────────────────────────────────

function makeScheduleSale(string $number, int $ttc): Sale
{
    $unit     = OrganizationalUnit::create(['name' => 'Sched Unit', 'active' => true]);
    $pos      = PointOfSale::create(['name' => 'Sched POS', 'organizational_unit_id' => $unit->id, 'active' => true]);
    $register = CashRegister::create(['name' => 'Caisse Sc', 'point_of_sale_id' => $pos->id, 'active' => true]);
    $user     = User::create(['first_name' => 'Sc', 'last_name' => 'User', 'phone' => '+237600660001', 'password' => bcrypt('x')]);

    $session = CashSession::create([
        'cash_register_id' => $register->id,
        'state'            => SessionState::Open,
        'opened_at'        => now(),
        'opening_balance'  => 0,
        'opened_by'        => $user->id,
    ]);

    $ht  = (int) round($ttc / 1.1925);
    $tax = $ttc - $ht;

    return Sale::create([
        'cash_session_id'     => $session->id,
        'state'               => SaleState::Confirmed,
        'number'              => $number,
        'total_excluding_tax' => $ht,
        'total_tax'           => $tax,
        'total_including_tax' => $ttc,
        'idempotency_key'     => 'sched-idem-' . $number,
        'confirmed_at'        => now(),
    ]);
}

// ─────────────────────────────────────────────────────────────────────────────
// I1 — Creating a valid schedule with deposit + installments = total succeeds
// ─────────────────────────────────────────────────────────────────────────────

it('create_schedule_with_balanced_amounts_succeeds', function (): void {
    $tenant = Tenant::create(['name' => 'Schedule Corp A', 'status' => 'actif']);
    tenancy()->initialize($tenant);

    $sale = makeScheduleSale('V-2026-SC01', 300000);

    $svc      = new PaymentScheduleService();
    $schedule = $svc->create($sale, 100000, [
        ['amount' => 100000, 'due_on' => '2026-10-01'],
        ['amount' => 100000, 'due_on' => '2026-11-01'],
    ]);

    expect($schedule->deposit)->toBe(100000);
    expect($schedule->total)->toBe(300000);
    expect(Installment::where('payment_schedule_id', $schedule->id)->count())->toBe(2);

    tenancy()->end();
});

// ─────────────────────────────────────────────────────────────────────────────
// I2 — Creating a schedule where deposit + installments ≠ total throws DomainException
// ─────────────────────────────────────────────────────────────────────────────

it('create_schedule_throws_when_amounts_do_not_balance', function (): void {
    $tenant = Tenant::create(['name' => 'Schedule Corp B', 'status' => 'actif']);
    tenancy()->initialize($tenant);

    $sale = makeScheduleSale('V-2026-SC02', 300000);

    $svc = new PaymentScheduleService();

    expect(fn () => $svc->create($sale, 100000, [
        ['amount' => 100000, 'due_on' => '2026-10-01'],
        ['amount' =>  50000, 'due_on' => '2026-11-01'],
    ]))->toThrow(\DomainException::class);

    tenancy()->end();
});

// ─────────────────────────────────────────────────────────────────────────────
// I3 — verify() returns true on a consistent schedule
// ─────────────────────────────────────────────────────────────────────────────

it('verify_returns_true_on_consistent_schedule', function (): void {
    $tenant = Tenant::create(['name' => 'Schedule Corp C', 'status' => 'actif']);
    tenancy()->initialize($tenant);

    $sale = makeScheduleSale('V-2026-SC03', 240000);

    $svc      = new PaymentScheduleService();
    $schedule = $svc->create($sale, 80000, [
        ['amount' => 80000, 'due_on' => '2026-10-01'],
        ['amount' => 80000, 'due_on' => '2026-11-01'],
    ]);

    expect($svc->verify($schedule))->toBeTrue();

    tenancy()->end();
});

// ─────────────────────────────────────────────────────────────────────────────
// I4 — Marking an installment as paid sets paid_at
// ─────────────────────────────────────────────────────────────────────────────

it('pay_installment_sets_paid_at', function (): void {
    $tenant = Tenant::create(['name' => 'Schedule Corp D', 'status' => 'actif']);
    tenancy()->initialize($tenant);

    $sale = makeScheduleSale('V-2026-SC04', 200000);

    $svc        = new PaymentScheduleService();
    $schedule   = $svc->create($sale, 100000, [
        ['amount' => 100000, 'due_on' => '2026-10-01'],
    ]);
    $installment = Installment::where('payment_schedule_id', $schedule->id)->firstOrFail();

    expect($installment->paid_at)->toBeNull();

    $svc->payInstallment($installment);
    $installment->refresh();

    expect($installment->paid_at)->not->toBeNull();

    tenancy()->end();
});
