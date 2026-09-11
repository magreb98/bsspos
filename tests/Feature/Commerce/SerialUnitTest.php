<?php

declare(strict_types=1);

/**
 * Feature tests for serial unit tracking — Task 7.1
 *
 * Decisions:
 *   D1 — SerialUnit: product_id + serial_number + status + sale_line_id
 *   D2 — SerialStatus enum: Available, Sold, InService
 *   D3 — SerialService::assign() guards INV-02 at service layer
 *   D4 — SerialService::release() restores availability
 *   D5 — DB UNIQUE on sale_line_id enforces INV-02 at storage layer
 */

use App\Control\Tenant;
use App\Platform\Identity\Models\User;
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
use Modules\SecteurElectronique\Services\SerialService;

uses(RefreshDatabase::class);

// ─────────────────────────────────────────────────────────────────────────────
// Helpers
// ─────────────────────────────────────────────────────────────────────────────

function makeSerialProduct(): Product
{
    $family = Family::create(['name' => 'Electronics', 'active' => true]);

    return Product::create([
        'reference'     => 'ELEC-001',
        'label'         => 'Smartphone',
        'family_id'     => $family->id,
        'selling_price' => 150000,
        'vat_rate'      => '19.25',
        'granularity'   => Granularity::Serial,
        'active'        => true,
    ]);
}

function makeSerialSaleLine(Product $product): SaleLine
{
    $unit     = OrganizationalUnit::create(['name' => 'Serial Unit', 'active' => true]);
    $pos      = PointOfSale::create(['name' => 'Serial POS', 'organizational_unit_id' => $unit->id, 'active' => true]);
    $register = CashRegister::create(['name' => 'Caisse Ser', 'point_of_sale_id' => $pos->id, 'active' => true]);
    $user     = User::create(['first_name' => 'Ser', 'last_name' => 'User', 'phone' => '+237600880001', 'password' => bcrypt('x')]);

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
        'number'              => 'V-2026-SER-01',
        'total_excluding_tax' => 125734,
        'total_tax'           => 24205,
        'total_including_tax' => 149939,
        'idempotency_key'     => 'ser-idem-01',
        'confirmed_at'        => now(),
    ]);

    return SaleLine::create([
        'sale_id'                  => $sale->id,
        'product_id'               => $product->id,
        'designation'              => 'Smartphone',
        'unit_price'               => 125734,
        'vat_rate'                 => '19.25',
        'quantity'                 => 1,
        'line_total_excluding_tax' => 125734,
        'line_total_tax'           => 24205,
        'line_total_including_tax' => 149939,
    ]);
}

// ─────────────────────────────────────────────────────────────────────────────
// S1 — Assigning an available unit sets status = sold and links sale_line_id
// ─────────────────────────────────────────────────────────────────────────────

it('assign_sets_status_sold_and_links_sale_line', function (): void {
    $tenant = Tenant::create(['name' => 'Serial Corp A', 'status' => 'actif']);
    tenancy()->initialize($tenant);

    $product = makeSerialProduct();
    $unit    = SerialUnit::create([
        'product_id'    => $product->id,
        'serial_number' => 'SN-001-ABC',
        'status'        => SerialStatus::Available,
    ]);

    $line = makeSerialSaleLine($product);

    $svc = new SerialService();
    $svc->assign($unit, $line);

    $unit->refresh();
    expect($unit->status)->toBe(SerialStatus::Sold);
    expect((string) $unit->sale_line_id)->toBe((string) $line->id);

    tenancy()->end();
});

// ─────────────────────────────────────────────────────────────────────────────
// S2 — Assigning an already-sold unit throws DomainException (INV-02)
// ─────────────────────────────────────────────────────────────────────────────

it('assign_throws_when_unit_already_sold', function (): void {
    $tenant = Tenant::create(['name' => 'Serial Corp B', 'status' => 'actif']);
    tenancy()->initialize($tenant);

    $product = makeSerialProduct();
    $unit    = SerialUnit::create([
        'product_id'    => $product->id,
        'serial_number' => 'SN-002-DEF',
        'status'        => SerialStatus::Available,
    ]);

    $line = makeSerialSaleLine($product);

    $svc = new SerialService();
    $svc->assign($unit, $line);

    expect(fn () => $svc->assign($unit, $line))->toThrow(\DomainException::class);

    tenancy()->end();
});

// ─────────────────────────────────────────────────────────────────────────────
// S3 — Releasing a sold unit restores status = available and clears sale_line_id
// ─────────────────────────────────────────────────────────────────────────────

it('release_restores_available_and_clears_sale_line', function (): void {
    $tenant = Tenant::create(['name' => 'Serial Corp C', 'status' => 'actif']);
    tenancy()->initialize($tenant);

    $product = makeSerialProduct();
    $unit    = SerialUnit::create([
        'product_id'    => $product->id,
        'serial_number' => 'SN-003-GHI',
        'status'        => SerialStatus::Available,
    ]);

    $line = makeSerialSaleLine($product);

    $svc = new SerialService();
    $svc->assign($unit, $line);
    $svc->releaseUnit($unit);

    $unit->refresh();
    expect($unit->status)->toBe(SerialStatus::Available);
    expect($unit->sale_line_id)->toBeNull();

    tenancy()->end();
});

// ─────────────────────────────────────────────────────────────────────────────
// S4 — DB unique constraint prevents two lines sharing the same serial unit
// ─────────────────────────────────────────────────────────────────────────────

it('db_unique_constraint_prevents_two_lines_sharing_serial_unit', function (): void {
    $tenant = Tenant::create(['name' => 'Serial Corp D', 'status' => 'actif']);
    tenancy()->initialize($tenant);

    $product = makeSerialProduct();
    $line    = makeSerialSaleLine($product);

    SerialUnit::create([
        'product_id'    => $product->id,
        'serial_number' => 'SN-004-JKL',
        'status'        => SerialStatus::Sold,
        'sale_line_id'  => $line->id,
    ]);

    expect(fn () => SerialUnit::create([
        'product_id'    => $product->id,
        'serial_number' => 'SN-004-JKL-CLONE',
        'status'        => SerialStatus::Sold,
        'sale_line_id'  => $line->id,
    ]))->toThrow(\Illuminate\Database\QueryException::class);

    tenancy()->end();
});
