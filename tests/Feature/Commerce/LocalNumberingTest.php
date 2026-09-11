<?php

declare(strict_types=1);

/**
 * Feature tests for per-POS local sale numbering — Task 5.3
 *
 * Invariant: each POS has an independent local counter per fiscal year.
 * Two boutiques may simultaneously hold V-{year}-0001 without conflict
 * because the sequence key is (pos_id, 'sale', fiscal_year).
 *
 * Decisions:
 *   D1 — AllocateNumber key = (pos_uuid, 'sale', fiscal_year)
 *   D2 — Number format: V-{year}-{seq:04d}
 *   D3 — Counters are isolated per POS: different POS restart at 1
 *   D4 — Counters are isolated per fiscal year: new year restarts at 1
 */

use App\Control\Tenant;
use App\Platform\Sequencing\Actions\AllocateNumber;
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
use Modules\Commerce\Internal\Services\SaleConfirmationService;
use Ramsey\Uuid\Uuid;

uses(RefreshDatabase::class);

// ─────────────────────────────────────────────────────────────────────────────
// Helpers
// ─────────────────────────────────────────────────────────────────────────────

function makeLocalPos(string $name): PointOfSale
{
    $unit = OrganizationalUnit::create(['name' => "{$name} HQ", 'active' => true]);

    return PointOfSale::create(['name' => $name, 'organizational_unit_id' => $unit->id, 'active' => true]);
}

function makeLocalSession(PointOfSale $pos): CashSession
{
    $register = CashRegister::create(['name' => "Reg-{$pos->name}", 'point_of_sale_id' => $pos->id, 'active' => true]);

    return CashSession::create([
        'cash_register_id' => $register->id,
        'state'            => SessionState::Open,
        'opened_at'        => now(),
        'opening_balance'  => 0,
        'opened_by'        => Uuid::uuid7()->toString(),
    ]);
}

function makeLocalProduct(string $ref): Product
{
    $family = Family::firstOrCreate(['name' => 'Local Numbering Family', 'active' => true]);

    return Product::create([
        'reference'     => $ref,
        'label'         => "Product {$ref}",
        'family_id'     => $family->id,
        'selling_price' => 5000,
        'vat_rate'      => '19.25',
        'granularity'   => Granularity::Service,
        'active'        => true,
    ]);
}

function makeDraftSale(CashSession $session, Product $product): Sale
{
    $sale = Sale::create([
        'cash_session_id' => $session->id,
        'state'           => SaleState::Draft,
        'idempotency_key' => Uuid::uuid7()->toString(),
    ]);

    SaleLine::create([
        'sale_id'                  => $sale->id,
        'product_id'               => $product->id,
        'designation'              => $product->label,
        'unit_price'               => 4994,
        'vat_rate'                 => '19.25',
        'quantity'                 => 1,
        'line_total_excluding_tax' => 4188,
        'line_total_tax'           => 806,
        'line_total_including_tax' => 4994,
    ]);

    return $sale;
}

// ─────────────────────────────────────────────────────────────────────────────
// L1 — Two boutiques each confirm a sale: each gets V-{year}-0001 independently
// ─────────────────────────────────────────────────────────────────────────────

it('two_pos_get_independent_local_sequences', function (): void {
    $tenant = Tenant::create(['name' => 'Local Num Corp A', 'status' => 'actif']);
    tenancy()->initialize($tenant);

    $posA    = makeLocalPos('POS Alpha');
    $posB    = makeLocalPos('POS Beta');
    $product = makeLocalProduct('LOC-001');

    $sessionA = makeLocalSession($posA);
    $sessionB = makeLocalSession($posB);

    $svc   = new SaleConfirmationService();
    $year  = now()->year;

    $saleA = makeDraftSale($sessionA, $product);
    $svc->confirm($saleA);
    $saleA->refresh();

    $saleB = makeDraftSale($sessionB, $product);
    $svc->confirm($saleB);
    $saleB->refresh();

    expect($saleA->number)->toBe("V-{$year}-0001");
    expect($saleB->number)->toBe("V-{$year}-0001");

    tenancy()->end();
});

// ─────────────────────────────────────────────────────────────────────────────
// L2 — Same boutique: sequential numbers without gaps
// ─────────────────────────────────────────────────────────────────────────────

it('same_pos_gets_sequential_numbers_without_gaps', function (): void {
    $tenant = Tenant::create(['name' => 'Local Num Corp B', 'status' => 'actif']);
    tenancy()->initialize($tenant);

    $pos     = makeLocalPos('POS Gamma');
    $product = makeLocalProduct('LOC-002');
    $session = makeLocalSession($pos);
    $svc     = new SaleConfirmationService();
    $year    = now()->year;

    $numbers = [];

    for ($i = 0; $i < 5; $i++) {
        $sale = makeDraftSale($session, $product);
        $svc->confirm($sale);
        $sale->refresh();
        $numbers[] = $sale->number;
    }

    expect($numbers)->toBe([
        "V-{$year}-0001",
        "V-{$year}-0002",
        "V-{$year}-0003",
        "V-{$year}-0004",
        "V-{$year}-0005",
    ]);

    tenancy()->end();
});

// ─────────────────────────────────────────────────────────────────────────────
// L3 — Same POS, two fiscal years: each year starts at 0001
// ─────────────────────────────────────────────────────────────────────────────

it('same_pos_restarts_sequence_each_fiscal_year', function (): void {
    $tenant = Tenant::create(['name' => 'Local Num Corp C', 'status' => 'actif']);
    tenancy()->initialize($tenant);

    $posId    = Uuid::fromString(makeLocalPos('POS Delta')->id);
    $allocator = new AllocateNumber();

    $allocator->initializeIfAbsent($posId, 'sale', 2025);
    $allocator->initializeIfAbsent($posId, 'sale', 2026);

    expect($allocator->allocate($posId, 'sale', 2025))->toBe(1);
    expect($allocator->allocate($posId, 'sale', 2025))->toBe(2);

    expect($allocator->allocate($posId, 'sale', 2026))->toBe(1);
    expect($allocator->allocate($posId, 'sale', 2026))->toBe(2);

    tenancy()->end();
});
