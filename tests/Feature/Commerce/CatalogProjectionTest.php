<?php

declare(strict_types=1);

/**
 * Feature tests for the catalog projection — Task 9.1
 *
 * Decisions:
 *   P1 — rebuild() creates a projection with correct selling_price and availability
 *   P2 — catalog_projections table has no cost_price column
 *   P3 — availability transitions correctly based on total stock quantity
 */

use App\Control\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Modules\Commerce\Internal\Enums\Granularity;
use Modules\Commerce\Internal\Models\Family;
use Modules\Commerce\Internal\Models\OrganizationalUnit;
use Modules\Commerce\Internal\Models\PointOfSale;
use Modules\Commerce\Internal\Models\Product;
use Modules\Commerce\Internal\Models\StockMovement;
use Modules\Commerce\Internal\Services\CatalogProjectionService;
use Modules\Commerce\Public\CatalogProjection;
use Modules\Commerce\Public\Enums\Availability;

uses(RefreshDatabase::class);

// ─────────────────────────────────────────────────────────────────────────────
// Helpers
// ─────────────────────────────────────────────────────────────────────────────

function makeCatalogProduct(string $ref, int $price = 10000): Product
{
    $family = Family::firstOrCreate(['name' => 'Catalog Family', 'active' => true]);

    return Product::create([
        'reference' => $ref,
        'label' => "Product {$ref}",
        'family_id' => $family->id,
        'selling_price' => $price,
        'vat_rate' => '19.25',
        'granularity' => Granularity::Quantity,
        'active' => true,
    ]);
}

function makeCatalogPos(string $name): PointOfSale
{
    $unit = OrganizationalUnit::create(['name' => "{$name} HQ", 'active' => true]);

    return PointOfSale::create(['name' => $name, 'organizational_unit_id' => $unit->id, 'active' => true]);
}

function addStock(PointOfSale $pos, Product $product, int $quantity): StockMovement
{
    return StockMovement::create([
        'point_of_sale_id' => $pos->id,
        'product_id' => $product->id,
        'quantity' => $quantity,
        'occurred_at' => now(),
    ]);
}

// ─────────────────────────────────────────────────────────────────────────────
// P1 — rebuild creates projection with correct selling_price and availability
// ─────────────────────────────────────────────────────────────────────────────

it('rebuild_creates_projection_with_correct_selling_price_and_availability', function (): void {
    $tenant = Tenant::create(['name' => 'Catalog Corp P1', 'status' => 'actif']);
    tenancy()->initialize($tenant);

    $pos = makeCatalogPos('P1 Store');
    $product = makeCatalogProduct('CAT-P1-001', 25000);
    addStock($pos, $product, 15);

    $service = new CatalogProjectionService();
    $projection = $service->rebuild($product);

    expect($projection)->toBeInstanceOf(CatalogProjection::class);
    expect($projection->selling_price)->toBe(25000);
    expect($projection->availability)->toBe(Availability::Available);
    expect($projection->product_id)->toBe($product->id);

    tenancy()->end();
});

// ─────────────────────────────────────────────────────────────────────────────
// P2 — schema test — catalog_projections table has no cost_price column
// ─────────────────────────────────────────────────────────────────────────────

it('catalog_projections_table_has_no_cost_price_column', function (): void {
    $tenant = Tenant::create(['name' => 'Catalog Corp P2', 'status' => 'actif']);
    tenancy()->initialize($tenant);

    expect(Schema::hasTable('catalog_projections'))->toBeTrue();
    expect(Schema::hasColumn('catalog_projections', 'cost_price'))->toBeFalse();
    expect(Schema::hasColumn('catalog_projections', 'margin'))->toBeFalse();
    expect(Schema::hasColumns('catalog_projections', [
        'id', 'product_id', 'reference', 'label', 'selling_price', 'availability', 'updated_at',
    ]))->toBeTrue();

    tenancy()->end();
});

// ─────────────────────────────────────────────────────────────────────────────
// P3 — availability transitions correctly
// ─────────────────────────────────────────────────────────────────────────────

it('availability_transitions_correctly_based_on_stock_quantity', function (): void {
    $tenant = Tenant::create(['name' => 'Catalog Corp P3', 'status' => 'actif']);
    tenancy()->initialize($tenant);

    $pos = makeCatalogPos('P3 Store');
    $service = new CatalogProjectionService();

    // out_of_stock when total = 0
    $product1 = makeCatalogProduct('CAT-P3-001');
    addStock($pos, $product1, 0);
    $p1 = $service->rebuild($product1);
    expect($p1->availability)->toBe(Availability::OutOfStock);

    // low_stock when 1-5
    $product2 = makeCatalogProduct('CAT-P3-002');
    addStock($pos, $product2, 3);
    $p2 = $service->rebuild($product2);
    expect($p2->availability)->toBe(Availability::LowStock);

    // available when > 10
    $product3 = makeCatalogProduct('CAT-P3-003');
    addStock($pos, $product3, 20);
    $p3 = $service->rebuild($product3);
    expect($p3->availability)->toBe(Availability::Available);

    tenancy()->end();
});
