<?php

declare(strict_types=1);

/**
 * Feature tests for supplier and reception — Task 4.1
 *
 * Decisions:
 *   D1 — suppliers, supplier_orders, supplier_order_lines tables
 *   D2 — receptions, reception_lines tables
 *   D3 — ReceptionService creates positive StockMovements for quantity products
 *   D4 — Service products generate no StockMovement
 */

use App\Control\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Commerce\Internal\Enums\Granularity;
use Modules\Commerce\Internal\Enums\SupplierOrderStatus;
use Modules\Commerce\Internal\Models\Family;
use Modules\Commerce\Internal\Models\OrganizationalUnit;
use Modules\Commerce\Internal\Models\PointOfSale;
use Modules\Commerce\Internal\Models\Product;
use Modules\Commerce\Internal\Models\Reception;
use Modules\Commerce\Internal\Models\StockMovement;
use Modules\Commerce\Internal\Models\Supplier;
use Modules\Commerce\Internal\Models\SupplierOrder;
use Modules\Commerce\Internal\Services\ReceptionService;

uses(RefreshDatabase::class);

// ─────────────────────────────────────────────────────────────────────────────
// Helpers
// ─────────────────────────────────────────────────────────────────────────────

function makeReceptionPos(): PointOfSale
{
    $unit = OrganizationalUnit::create(['name' => 'Reception HQ', 'active' => true]);

    return PointOfSale::create(['name' => 'Reception POS', 'organizational_unit_id' => $unit->id, 'active' => true]);
}

function makeReceptionSupplier(): Supplier
{
    return Supplier::create(['name' => 'Test Supplier', 'active' => true]);
}

function makeReceptionOrder(Supplier $supplier): SupplierOrder
{
    return SupplierOrder::create([
        'supplier_id' => $supplier->id,
        'status'      => SupplierOrderStatus::Sent,
        'ordered_at'  => now(),
    ]);
}

function makeReceptionProduct(string $ref, Granularity $granularity): Product
{
    $family = Family::firstOrCreate(['name' => 'Reception Family', 'active' => true]);

    return Product::create([
        'reference'     => $ref,
        'label'         => "Product {$ref}",
        'family_id'     => $family->id,
        'selling_price' => 10000,
        'vat_rate'      => '19.25',
        'granularity'   => $granularity,
        'active'        => true,
    ]);
}

// ─────────────────────────────────────────────────────────────────────────────
// T1 — Reception creates positive StockMovements for each quantity product
// ─────────────────────────────────────────────────────────────────────────────

it('reception_creates_positive_stock_movements', function (): void {
    $tenant = Tenant::create(['name' => 'Reception Corp A', 'status' => 'actif']);
    tenancy()->initialize($tenant);

    $pos      = makeReceptionPos();
    $supplier = makeReceptionSupplier();
    $order    = makeReceptionOrder($supplier);
    $product  = makeReceptionProduct('REC-001', Granularity::Quantity);

    $reception = (new ReceptionService())->receive($order, $pos, [
        [
            'product_id'        => $product->id,
            'quantity_expected' => 20,
            'quantity_received' => 20,
            'unit_cost'         => 5000,
        ],
    ]);

    expect($reception)->toBeInstanceOf(Reception::class);
    expect($reception->lines()->count())->toBe(1);

    $movement = StockMovement::where('product_id', $product->id)->firstOrFail();
    expect($movement->quantity)->toBe(20);
    expect($movement->point_of_sale_id)->toBe($pos->id);

    $order->refresh();
    expect($order->status)->toBe(SupplierOrderStatus::Received);

    tenancy()->end();
});

// ─────────────────────────────────────────────────────────────────────────────
// T2 — Stock level (SUM movements) equals received quantity after reception
// ─────────────────────────────────────────────────────────────────────────────

it('stock_level_equals_received_quantity_after_reception', function (): void {
    $tenant = Tenant::create(['name' => 'Reception Corp B', 'status' => 'actif']);
    tenancy()->initialize($tenant);

    $pos     = makeReceptionPos();
    $product = makeReceptionProduct('REC-002', Granularity::Quantity);

    $supplier = makeReceptionSupplier();
    $order    = makeReceptionOrder($supplier);

    (new ReceptionService())->receive($order, $pos, [
        [
            'product_id'        => $product->id,
            'quantity_expected' => 50,
            'quantity_received' => 50,
            'unit_cost'         => 3000,
        ],
    ]);

    $netStock = (int) StockMovement::where('product_id', $product->id)->sum('quantity');

    expect($netStock)->toBe(50);

    tenancy()->end();
});

// ─────────────────────────────────────────────────────────────────────────────
// T3 — Receiving fewer units than ordered creates movements for actual quantity
// ─────────────────────────────────────────────────────────────────────────────

it('partial_reception_creates_movement_for_actual_received_quantity', function (): void {
    $tenant = Tenant::create(['name' => 'Reception Corp C', 'status' => 'actif']);
    tenancy()->initialize($tenant);

    $pos     = makeReceptionPos();
    $product = makeReceptionProduct('REC-003', Granularity::Quantity);

    $supplier = makeReceptionSupplier();
    $order    = makeReceptionOrder($supplier);

    (new ReceptionService())->receive($order, $pos, [
        [
            'product_id'        => $product->id,
            'quantity_expected' => 100,
            'quantity_received' => 60,
            'unit_cost'         => 2000,
        ],
    ]);

    $line = Reception::firstOrFail()->lines()->firstOrFail();
    expect($line->quantity_expected)->toBe(100);
    expect($line->quantity_received)->toBe(60);

    $netStock = (int) StockMovement::where('product_id', $product->id)->sum('quantity');
    expect($netStock)->toBe(60);

    tenancy()->end();
});

// ─────────────────────────────────────────────────────────────────────────────
// T4 — Service product in reception creates no StockMovement
// ─────────────────────────────────────────────────────────────────────────────

it('service_product_reception_creates_no_stock_movement', function (): void {
    $tenant = Tenant::create(['name' => 'Reception Corp D', 'status' => 'actif']);
    tenancy()->initialize($tenant);

    $pos     = makeReceptionPos();
    $product = makeReceptionProduct('REC-004', Granularity::Service);

    $supplier = makeReceptionSupplier();
    $order    = makeReceptionOrder($supplier);

    (new ReceptionService())->receive($order, $pos, [
        [
            'product_id'        => $product->id,
            'quantity_expected' => 5,
            'quantity_received' => 5,
            'unit_cost'         => 0,
        ],
    ]);

    expect(StockMovement::count())->toBe(0);

    tenancy()->end();
});
