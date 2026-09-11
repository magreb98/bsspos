<?php

declare(strict_types=1);

/**
 * Feature tests for stock anomaly on offline sync — Task 3.2
 *
 * Decisions:
 *   D1 — sync_anomalies table
 *   D2 — SaleConfirmationService creates SyncAnomaly when stock goes negative
 *   D3 — OfflineSyncService counts anomalies
 *   D4 — Sale is never rejected regardless of stock level
 */

use App\Control\Tenant;
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
use Modules\Commerce\Internal\Models\StockMovement;
use Modules\Commerce\Internal\Models\SyncAnomaly;
use Modules\Commerce\Internal\Services\OfflineSyncService;
use Modules\Commerce\Internal\Services\SaleConfirmationService;
use Modules\Commerce\Internal\Sync\OfflineSaleRequest;
use Ramsey\Uuid\Uuid;

uses(RefreshDatabase::class);

// ─────────────────────────────────────────────────────────────────────────────
// Helpers
// ─────────────────────────────────────────────────────────────────────────────

function makeQuantityProduct(string $ref): Product
{
    $family = Family::firstOrCreate(['name' => 'Anomaly Family', 'active' => true]);

    return Product::create([
        'reference'     => $ref,
        'label'         => "Product {$ref}",
        'family_id'     => $family->id,
        'selling_price' => 10000,
        'vat_rate'      => '19.25',
        'granularity'   => Granularity::Quantity,
        'active'        => true,
    ]);
}

function makeOpenSessionForAnomaly(): CashSession
{
    $unit     = OrganizationalUnit::create(['name' => 'Anomaly HQ', 'active' => true]);
    $pos      = PointOfSale::create(['name' => 'Anomaly POS', 'organizational_unit_id' => $unit->id, 'active' => true]);
    $register = CashRegister::create(['name' => 'Anomaly R1', 'point_of_sale_id' => $pos->id, 'active' => true]);

    return CashSession::create([
        'cash_register_id' => $register->id,
        'state'            => SessionState::Open,
        'opened_at'        => now(),
        'opening_balance'  => 0,
        'opened_by'        => Uuid::uuid7()->toString(),
    ]);
}

// ─────────────────────────────────────────────────────────────────────────────
// T1 — sync_anomalies table has required columns
// ─────────────────────────────────────────────────────────────────────────────

it('sync_anomalies_table_has_required_columns', function (): void {
    $tenant = Tenant::create(['name' => 'Anomaly Corp A', 'status' => 'actif']);
    tenancy()->initialize($tenant);

    $columns = \Illuminate\Support\Facades\Schema::getColumnListing('sync_anomalies');

    expect($columns)->toContain('id', 'sale_id', 'product_id', 'type', 'detail', 'resolved_at');

    tenancy()->end();
});

// ─────────────────────────────────────────────────────────────────────────────
// T2 — sale that makes stock negative: confirmed + anomaly created
// ─────────────────────────────────────────────────────────────────────────────

it('sale_making_stock_negative_is_confirmed_with_anomaly', function (): void {
    $tenant = Tenant::create(['name' => 'Anomaly Corp B', 'status' => 'actif']);
    tenancy()->initialize($tenant);

    $session = makeOpenSessionForAnomaly();
    $product = makeQuantityProduct('ANOM-001');

    // Sell 5 units with no prior stock — stock will be -5
    $sale = Sale::create([
        'cash_session_id' => $session->id,
        'state'           => SaleState::Draft,
        'idempotency_key' => Uuid::uuid7()->toString(),
    ]);

    SaleLine::create([
        'sale_id'                  => $sale->id,
        'product_id'               => $product->id,
        'designation'              => $product->label,
        'unit_price'               => 10000,
        'vat_rate'                 => '19.25',
        'quantity'                 => 5,
        'line_total_excluding_tax' => 41920,
        'line_total_tax'           => 8080,
        'line_total_including_tax' => 50000,
    ]);

    (new SaleConfirmationService())->confirm($sale);

    $sale->refresh();

    expect($sale->state->value)->toBe('confirmed');
    expect(SyncAnomaly::where('sale_id', $sale->id)->count())->toBe(1);

    $anomaly = SyncAnomaly::where('sale_id', $sale->id)->firstOrFail();
    expect($anomaly->type)->toBe('stock_conflict');
    expect($anomaly->product_id)->toBe($product->id);

    tenancy()->end();
});

// ─────────────────────────────────────────────────────────────────────────────
// T3 — normal sale (stock stays positive): confirmed, no anomaly
// ─────────────────────────────────────────────────────────────────────────────

it('normal_sale_creates_no_anomaly', function (): void {
    $tenant = Tenant::create(['name' => 'Anomaly Corp C', 'status' => 'actif']);
    tenancy()->initialize($tenant);

    $session = makeOpenSessionForAnomaly();
    $product  = makeQuantityProduct('ANOM-002');
    $register = $session->cashRegister ?? throw new \DomainException('No register.');
    $pos      = $register->pointOfSale ?? throw new \DomainException('No point of sale.');

    // Prime the stock with +10 units
    StockMovement::create([
        'point_of_sale_id' => $pos->id,
        'product_id'       => $product->id,
        'sale_line_id'     => null,
        'quantity'         => 10,
        'occurred_at'      => now(),
    ]);

    $sale = Sale::create([
        'cash_session_id' => $session->id,
        'state'           => SaleState::Draft,
        'idempotency_key' => Uuid::uuid7()->toString(),
    ]);

    SaleLine::create([
        'sale_id'                  => $sale->id,
        'product_id'               => $product->id,
        'designation'              => $product->label,
        'unit_price'               => 10000,
        'vat_rate'                 => '19.25',
        'quantity'                 => 3,
        'line_total_excluding_tax' => 25149,
        'line_total_tax'           => 4851,
        'line_total_including_tax' => 30000,
    ]);

    (new SaleConfirmationService())->confirm($sale);

    expect(SyncAnomaly::count())->toBe(0);

    tenancy()->end();
});

// ─────────────────────────────────────────────────────────────────────────────
// T4 — offline sync: anomaly counted in OfflineSyncResult
// ─────────────────────────────────────────────────────────────────────────────

it('offline_sync_counts_stock_anomalies', function (): void {
    $tenant = Tenant::create(['name' => 'Anomaly Corp D', 'status' => 'actif']);
    tenancy()->initialize($tenant);

    $unit     = OrganizationalUnit::create(['name' => 'Anom HQ D', 'active' => true]);
    $pos      = PointOfSale::create(['name' => 'Anom POS D', 'organizational_unit_id' => $unit->id, 'active' => true]);
    $register = CashRegister::create(['name' => 'Anom R1 D', 'point_of_sale_id' => $pos->id, 'active' => true]);

    $session = CashSession::create([
        'cash_register_id' => $register->id,
        'state'            => SessionState::Open,
        'opened_at'        => now(),
        'opening_balance'  => 0,
        'opened_by'        => Uuid::uuid7()->toString(),
    ]);

    $product = makeQuantityProduct('ANOM-D001');
    $device  = 'device-anom';

    $request = new OfflineSaleRequest(
        idempotencyKey: "{$device}:1",
        cashSessionId:  $session->id,
        lines: [[
            'product_id'               => $product->id,
            'quantity'                 => 10,
            'designation'              => $product->label,
            'unit_price'               => 10000,
            'vat_rate'                 => '19.25',
            'line_total_excluding_tax' => 83830,
            'line_total_tax'           => 16170,
            'line_total_including_tax' => 100000,
        ]],
    );

    $result = (new OfflineSyncService())->sync([$request]);

    expect($result->processed)->toBe(1);
    expect($result->anomalies)->toBe(1);
    expect(SyncAnomaly::count())->toBe(1);

    tenancy()->end();
});
