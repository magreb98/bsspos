<?php

declare(strict_types=1);

/**
 * Sector isolation tests — Task 7.5
 *
 * Decisions:
 *   D1 — No CommerceServiceProvider → sector services never auto-bound in container
 *   D2 — Full base commerce path must work with zero sector code
 *   D3 — Container must not resolve sector services automatically
 */

use App\Control\Tenant;
use App\Platform\Identity\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Commerce\Internal\Enums\SaleState;
use Modules\Commerce\Internal\Enums\SessionState;
use Modules\Commerce\Internal\Models\CashRegister;
use Modules\Commerce\Internal\Models\CashSession;
use Modules\Commerce\Internal\Models\OrganizationalUnit;
use Modules\Commerce\Internal\Models\PointOfSale;
use Modules\Commerce\Internal\Models\Sale;
use Modules\Commerce\Internal\Services\DailyAggregateService;
use Modules\Commerce\Internal\Services\DashboardService;
use Modules\SecteurElectronique\Services\PaymentScheduleService;
use Modules\SecteurElectronique\Services\SerialService;
use Modules\SecteurElectronique\Services\ServiceTicketService;
use Modules\SecteurElectronique\Services\WarrantyService;

uses(RefreshDatabase::class);

// ─────────────────────────────────────────────────────────────────────────────
// X1 — Full base commerce path completes without any sector service
// ─────────────────────────────────────────────────────────────────────────────

it('base_commerce_path_works_without_sector_services', function (): void {
    $tenant = Tenant::create(['name' => 'Isolation Corp A', 'status' => 'actif']);
    tenancy()->initialize($tenant);

    Carbon::setTestNow('2026-09-01');

    $unit     = OrganizationalUnit::create(['name' => 'Iso Unit', 'active' => true]);
    $pos      = PointOfSale::create(['name' => 'Iso POS', 'organizational_unit_id' => $unit->id, 'active' => true]);
    $register = CashRegister::create(['name' => 'Iso Caisse', 'point_of_sale_id' => $pos->id, 'active' => true]);
    $user     = User::create(['first_name' => 'Iso', 'last_name' => 'User', 'phone' => '+237600550001', 'password' => bcrypt('x')]);

    $session = CashSession::create([
        'cash_register_id' => $register->id,
        'state'            => SessionState::Open,
        'opened_at'        => now(),
        'opening_balance'  => 0,
        'opened_by'        => $user->id,
    ]);

    Sale::create([
        'cash_session_id'     => $session->id,
        'state'               => SaleState::Confirmed,
        'number'              => 'V-2026-ISO01',
        'total_excluding_tax' => 83765,
        'total_tax'           => 16124,
        'total_including_tax' => 99889,
        'idempotency_key'     => 'iso-idem-01',
        'confirmed_at'        => now(),
    ]);

    $aggregateSvc = new DailyAggregateService();
    $aggregate    = $aggregateSvc->recompute($pos, Carbon::parse('2026-09-01'));
    expect($aggregate->sale_count)->toBe(1);

    $pos->load('organizationalUnit');
    $orgUnit     = $pos->organizationalUnit ?? throw new \RuntimeException('No unit');
    $user->perimeters()->attach($orgUnit->perimeter_id);

    $dashboardSvc = new DashboardService();
    $result       = $dashboardSvc->consolidate($user, Carbon::parse('2026-09-01'), Carbon::parse('2026-09-01'));
    expect($result->totalSaleCount)->toBe(1);
    expect($result->totalIncludingTax)->toBe(99889);

    Carbon::setTestNow();
    tenancy()->end();
});

// ─────────────────────────────────────────────────────────────────────────────
// X2 — Sector services are not bound in the Laravel container
// ─────────────────────────────────────────────────────────────────────────────

it('sector_services_are_not_bound_in_container', function (): void {
    expect(app()->bound(SerialService::class))->toBeFalse();
    expect(app()->bound(WarrantyService::class))->toBeFalse();
    expect(app()->bound(ServiceTicketService::class))->toBeFalse();
    expect(app()->bound(PaymentScheduleService::class))->toBeFalse();
});
