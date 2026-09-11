<?php

declare(strict_types=1);

/**
 * Feature tests for inventory count — Task 4.2
 *
 * Decisions:
 *   D1 — inventory_counts table
 *   D2 — inventory_count_lines table (theoretical, counted, adjustment)
 *   D3 — InventoryService creates adjustment StockMovements when discrepancy found
 *   D4 — Zero discrepancy → no StockMovement created
 */

use App\Control\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Commerce\Internal\Enums\Granularity;
use Modules\Commerce\Internal\Models\Family;
use Modules\Commerce\Internal\Models\InventoryCount;
use Modules\Commerce\Internal\Models\InventoryCountLine;
use Modules\Commerce\Internal\Models\OrganizationalUnit;
use Modules\Commerce\Internal\Models\PointOfSale;
use Modules\Commerce\Internal\Models\Product;
use Modules\Commerce\Internal\Models\StockMovement;
use Modules\Commerce\Internal\Services\InventoryService;

uses(RefreshDatabase::class);

// ─────────────────────────────────────────────────────────────────────────────
// Helpers
// ─────────────────────────────────────────────────────────────────────────────

function makeInventoryPos(): PointOfSale
{
    $unit = OrganizationalUnit::create(['name' => 'Inventory HQ', 'active' => true]);

    return PointOfSale::create(['name' => 'Inventory POS', 'organizational_unit_id' => $unit->id, 'active' => true]);
}

function makeInventoryProduct(string $ref): Product
{
    $family = Family::firstOrCreate(['name' => 'Inventory Family', 'active' => true]);

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

// ─────────────────────────────────────────────────────────────────────────────
// T1 — Surplus found: positive adjustment StockMovement created
// ─────────────────────────────────────────────────────────────────────────────

it('inventory_surplus_creates_positive_adjustment_movement', function (): void {
    $tenant = Tenant::create(['name' => 'Inv Corp A', 'status' => 'actif']);
    tenancy()->initialize($tenant);

    $pos     = makeInventoryPos();
    $product = makeInventoryProduct('INV-001');

    // Theoretical: 10 units in system
    StockMovement::create([
        'point_of_sale_id' => $pos->id,
        'product_id'       => $product->id,
        'sale_line_id'     => null,
        'quantity'         => 10,
        'occurred_at'      => now(),
    ]);

    // Physical count: 13 units found → surplus of +3
    $inventoryCount = (new InventoryService())->count($pos, [
        ['product_id' => $product->id, 'counted_quantity' => 13],
    ]);

    expect($inventoryCount)->toBeInstanceOf(InventoryCount::class);

    $line = InventoryCountLine::firstOrFail();
    expect($line->theoretical_quantity)->toBe(10);
    expect($line->counted_quantity)->toBe(13);
    expect($line->adjustment)->toBe(3);

    // Two movements: initial +10 and adjustment +3
    $movements = StockMovement::where('product_id', $product->id)->get();
    expect($movements)->toHaveCount(2);
    expect(($movements->last() ?? throw new \DomainException('No movement.'))->quantity)->toBe(3);

    tenancy()->end();
});

// ─────────────────────────────────────────────────────────────────────────────
// T2 — Loss found: negative adjustment StockMovement created
// ─────────────────────────────────────────────────────────────────────────────

it('inventory_loss_creates_negative_adjustment_movement', function (): void {
    $tenant = Tenant::create(['name' => 'Inv Corp B', 'status' => 'actif']);
    tenancy()->initialize($tenant);

    $pos     = makeInventoryPos();
    $product = makeInventoryProduct('INV-002');

    // Theoretical: 20 units
    StockMovement::create([
        'point_of_sale_id' => $pos->id,
        'product_id'       => $product->id,
        'sale_line_id'     => null,
        'quantity'         => 20,
        'occurred_at'      => now(),
    ]);

    // Physical count: 15 units → loss of -5
    (new InventoryService())->count($pos, [
        ['product_id' => $product->id, 'counted_quantity' => 15],
    ]);

    $line = InventoryCountLine::firstOrFail();
    expect($line->theoretical_quantity)->toBe(20);
    expect($line->counted_quantity)->toBe(15);
    expect($line->adjustment)->toBe(-5);

    $adjustmentMovement = StockMovement::where('product_id', $product->id)
        ->where('quantity', '<', 0)
        ->firstOrFail();
    expect($adjustmentMovement->quantity)->toBe(-5);

    tenancy()->end();
});

// ─────────────────────────────────────────────────────────────────────────────
// T3 — Exact match: no adjustment StockMovement, line records correct values
// ─────────────────────────────────────────────────────────────────────────────

it('inventory_exact_match_creates_no_stock_movement', function (): void {
    $tenant = Tenant::create(['name' => 'Inv Corp C', 'status' => 'actif']);
    tenancy()->initialize($tenant);

    $pos     = makeInventoryPos();
    $product = makeInventoryProduct('INV-003');

    StockMovement::create([
        'point_of_sale_id' => $pos->id,
        'product_id'       => $product->id,
        'sale_line_id'     => null,
        'quantity'         => 30,
        'occurred_at'      => now(),
    ]);

    (new InventoryService())->count($pos, [
        ['product_id' => $product->id, 'counted_quantity' => 30],
    ]);

    $line = InventoryCountLine::firstOrFail();
    expect($line->adjustment)->toBe(0);

    // Only the initial movement, no adjustment
    expect(StockMovement::count())->toBe(1);

    tenancy()->end();
});

// ─────────────────────────────────────────────────────────────────────────────
// T4 — After adjustment, SUM(movements) equals counted_quantity (INV-01)
// ─────────────────────────────────────────────────────────────────────────────

it('after_inventory_adjustment_sum_of_movements_equals_counted_quantity', function (): void {
    $tenant = Tenant::create(['name' => 'Inv Corp D', 'status' => 'actif']);
    tenancy()->initialize($tenant);

    $pos     = makeInventoryPos();
    $product = makeInventoryProduct('INV-004');

    // Series of movements: +50, -8
    StockMovement::create(['point_of_sale_id' => $pos->id, 'product_id' => $product->id, 'sale_line_id' => null, 'quantity' => 50, 'occurred_at' => now()]);
    StockMovement::create(['point_of_sale_id' => $pos->id, 'product_id' => $product->id, 'sale_line_id' => null, 'quantity' => -8, 'occurred_at' => now()]);

    // Theoretical = 42, physical count = 40 → adjustment = -2
    (new InventoryService())->count($pos, [
        ['product_id' => $product->id, 'counted_quantity' => 40],
    ]);

    $netStock = (int) StockMovement::where('product_id', $product->id)->sum('quantity');
    expect($netStock)->toBe(40);

    tenancy()->end();
});
