<?php

declare(strict_types=1);

/**
 * Feature tests for warranty management — Task 7.2
 *
 * Decisions:
 *   D1 — Warranty: one per serial unit, covers starts_on → expires_on
 *   D2 — WarrantyService::issue() validates unit is sold, issues are idempotency-guarded
 *   D3 — WarrantyService::isActive() checks today vs expires_on
 *   D4 — DB UNIQUE on serial_unit_id enforces one warranty per unit
 */

use App\Control\Tenant;
use App\Platform\Identity\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Commerce\Internal\Enums\Granularity;
use Modules\Commerce\Internal\Enums\SaleState;
use Modules\Commerce\Internal\Enums\SessionState;
use Modules\Commerce\Internal\Models\CashRegister;
use Modules\Commerce\Internal\Models\CashSession;
use Modules\Commerce\Internal\Models\Family;
use Modules\Commerce\Internal\Models\OrganizationalUnit;
use Modules\Commerce\Internal\Models\PointOfSale;
use Modules\Commerce\Internal\Models\Product;
use Modules\Commerce\Internal\Models\Sale;
use Modules\Commerce\Internal\Models\SaleLine;
use Modules\SecteurElectronique\Enums\SerialStatus;
use Modules\SecteurElectronique\Models\SerialUnit;
use Modules\SecteurElectronique\Models\Warranty;
use Modules\SecteurElectronique\Services\WarrantyService;

uses(RefreshDatabase::class);

// ─────────────────────────────────────────────────────────────────────────────
// Helpers
// ─────────────────────────────────────────────────────────────────────────────

/** @return array{Product, SaleLine} */
function makeWarrantySaleLine(): array
{
    $family   = Family::create(['name' => 'Elec W', 'active' => true]);
    $product  = Product::create([
        'reference'     => 'ELEC-W01',
        'label'         => 'TV',
        'family_id'     => $family->id,
        'selling_price' => 200000,
        'vat_rate'      => '19.25',
        'granularity'   => Granularity::Serial,
        'active'        => true,
    ]);
    $unit2     = OrganizationalUnit::create(['name' => 'War Unit', 'active' => true]);
    $pos      = PointOfSale::create(['name' => 'War POS', 'organizational_unit_id' => $unit2->id, 'active' => true]);
    $register = CashRegister::create(['name' => 'Caisse W', 'point_of_sale_id' => $pos->id, 'active' => true]);
    $user     = User::create(['first_name' => 'W', 'last_name' => 'User', 'phone' => '+237600770001', 'password' => bcrypt('x')]);

    $session = CashSession::create([
        'cash_register_id' => $register->id,
        'state'            => SessionState::Open,
        'opened_at'        => now(),
        'opening_balance'  => 0,
        'opened_by'        => $user->id,
    ]);

    $sale = Sale::create([
        'cash_session_id'     => $session->id,
        'state'               => SaleState::Confirmed,
        'number'              => 'V-2026-W01',
        'total_excluding_tax' => 167643,
        'total_tax'           => 32270,
        'total_including_tax' => 199913,
        'idempotency_key'     => 'war-idem-01',
        'confirmed_at'        => now(),
    ]);

    $line = SaleLine::create([
        'sale_id'                  => $sale->id,
        'product_id'               => $product->id,
        'designation'              => 'TV',
        'unit_price'               => 167643,
        'vat_rate'                 => '19.25',
        'quantity'                 => 1,
        'line_total_excluding_tax' => 167643,
        'line_total_tax'           => 32270,
        'line_total_including_tax' => 199913,
    ]);

    return [$product, $line];
}

// ─────────────────────────────────────────────────────────────────────────────
// W1 — Issuing a warranty for a sold unit creates a Warranty with correct dates
// ─────────────────────────────────────────────────────────────────────────────

it('issue_creates_warranty_with_correct_dates', function (): void {
    $tenant = Tenant::create(['name' => 'Warranty Corp A', 'status' => 'actif']);
    tenancy()->initialize($tenant);

    [$product, $line] = makeWarrantySaleLine();
    $unit = SerialUnit::create([
        'product_id'    => $product->id,
        'serial_number' => 'TV-SN-001',
        'status'        => SerialStatus::Sold,
        'sale_line_id'  => $line->id,
    ]);

    Carbon::setTestNow('2026-09-01');

    $svc      = new WarrantyService();
    $warranty = $svc->issue($unit, $line, 12);

    expect($warranty->starts_on)->toBe('2026-09-01');
    expect($warranty->expires_on)->toBe('2027-09-01');
    expect($warranty->duration_months)->toBe(12);
    expect((string) $warranty->serial_unit_id)->toBe((string) $unit->id);

    Carbon::setTestNow();
    tenancy()->end();
});

// ─────────────────────────────────────────────────────────────────────────────
// W2 — Issuing a warranty for an unsold unit throws DomainException
// ─────────────────────────────────────────────────────────────────────────────

it('issue_throws_when_unit_not_sold', function (): void {
    $tenant = Tenant::create(['name' => 'Warranty Corp B', 'status' => 'actif']);
    tenancy()->initialize($tenant);

    [$product, $line] = makeWarrantySaleLine();
    $unit = SerialUnit::create([
        'product_id'    => $product->id,
        'serial_number' => 'TV-SN-002',
        'status'        => SerialStatus::Available,
    ]);

    $svc = new WarrantyService();

    expect(fn () => $svc->issue($unit, $line, 12))->toThrow(\DomainException::class);

    tenancy()->end();
});

// ─────────────────────────────────────────────────────────────────────────────
// W3 — Issuing a second warranty for the same unit throws DomainException
// ─────────────────────────────────────────────────────────────────────────────

it('issue_throws_when_warranty_already_exists', function (): void {
    $tenant = Tenant::create(['name' => 'Warranty Corp C', 'status' => 'actif']);
    tenancy()->initialize($tenant);

    [$product, $line] = makeWarrantySaleLine();
    $unit = SerialUnit::create([
        'product_id'    => $product->id,
        'serial_number' => 'TV-SN-003',
        'status'        => SerialStatus::Sold,
        'sale_line_id'  => $line->id,
    ]);

    $svc = new WarrantyService();
    $svc->issue($unit, $line, 12);

    expect(fn () => $svc->issue($unit, $line, 24))->toThrow(\DomainException::class);

    tenancy()->end();
});

// ─────────────────────────────────────────────────────────────────────────────
// W4 — isActive returns true within period, false after expiry
// ─────────────────────────────────────────────────────────────────────────────

it('is_active_returns_true_within_period_false_after', function (): void {
    $tenant = Tenant::create(['name' => 'Warranty Corp D', 'status' => 'actif']);
    tenancy()->initialize($tenant);

    [$product, $line] = makeWarrantySaleLine();
    $unit = SerialUnit::create([
        'product_id'    => $product->id,
        'serial_number' => 'TV-SN-004',
        'status'        => SerialStatus::Sold,
        'sale_line_id'  => $line->id,
    ]);

    Carbon::setTestNow('2026-09-01');
    $svc      = new WarrantyService();
    $warranty = $svc->issue($unit, $line, 6);

    Carbon::setTestNow('2027-02-28');
    expect($svc->isActive($warranty))->toBeTrue();

    Carbon::setTestNow('2027-03-02');
    expect($svc->isActive($warranty))->toBeFalse();

    Carbon::setTestNow();
    tenancy()->end();
});
