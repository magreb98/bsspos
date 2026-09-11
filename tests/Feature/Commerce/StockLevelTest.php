<?php

declare(strict_types=1);

/**
 * Feature tests for stock levels and variants — Task 1.4
 *
 * Decisions:
 *   D1 — product_variants: uuid PK, product_id FK, label, reference (unique), active
 *   D2 — stock_levels: uuid PK, point_of_sale_id FK, product_id nullable, product_variant_id nullable, quantity bigint
 *   D5 — no StockLevel for Service granularity products
 */

use App\Control\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Modules\Commerce\Internal\Enums\Granularity;
use Modules\Commerce\Internal\Models\Family;
use Modules\Commerce\Internal\Models\OrganizationalUnit;
use Modules\Commerce\Internal\Models\PointOfSale;
use Modules\Commerce\Internal\Models\Product;
use Modules\Commerce\Internal\Models\ProductVariant;
use Modules\Commerce\Internal\Models\StockLevel;

uses(RefreshDatabase::class);

// ─────────────────────────────────────────────────────────────────────────────
// T1 — product_variants table has required columns
// ─────────────────────────────────────────────────────────────────────────────

it('product_variants_table_has_required_columns', function (): void {
    $tenant = Tenant::create(['name' => 'Variant Corp', 'status' => 'actif']);
    tenancy()->initialize($tenant);

    expect(Schema::hasTable('product_variants'))->toBeTrue();
    expect(Schema::hasColumns('product_variants', ['id', 'product_id', 'label', 'reference', 'active']))->toBeTrue();

    tenancy()->end();
});

// ─────────────────────────────────────────────────────────────────────────────
// T2 — stock_levels table has required columns
// ─────────────────────────────────────────────────────────────────────────────

it('stock_levels_table_has_required_columns', function (): void {
    $tenant = Tenant::create(['name' => 'Stock Corp', 'status' => 'actif']);
    tenancy()->initialize($tenant);

    expect(Schema::hasTable('stock_levels'))->toBeTrue();
    expect(Schema::hasColumns('stock_levels', [
        'id', 'point_of_sale_id', 'product_id', 'product_variant_id', 'quantity',
    ]))->toBeTrue();

    tenancy()->end();
});

// ─────────────────────────────────────────────────────────────────────────────
// T3 — ProductVariant::product() returns the product
// ─────────────────────────────────────────────────────────────────────────────

it('product_variant_belongs_to_product', function (): void {
    $tenant = Tenant::create(['name' => 'Variant Rel Corp', 'status' => 'actif']);
    tenancy()->initialize($tenant);

    $family  = Family::create(['name' => 'Mode']);
    $product = Product::create([
        'reference'     => 'SHIRT-001',
        'label'         => 'Chemise',
        'family_id'     => $family->id,
        'selling_price' => 8000,
        'granularity'   => Granularity::Variant,
    ]);

    $variant = ProductVariant::create([
        'product_id' => $product->id,
        'label'      => 'Bleu XL',
        'reference'  => 'SHIRT-001-BXL',
    ]);

    expect(($variant->product ?? throw new \DomainException('No product.'))->id)->toBe($product->id);

    tenancy()->end();
});

// ─────────────────────────────────────────────────────────────────────────────
// T4 — StockLevel::isAvailable() reflects quantity
// ─────────────────────────────────────────────────────────────────────────────

it('stock_level_is_available_reflects_quantity', function (): void {
    $tenant = Tenant::create(['name' => 'Avail Corp', 'status' => 'actif']);
    tenancy()->initialize($tenant);

    $unit    = OrganizationalUnit::create(['name' => 'Douala']);
    $pos     = PointOfSale::create(['name' => 'Boutique', 'organizational_unit_id' => $unit->id]);
    $family  = Family::create(['name' => 'Divers']);
    $product = Product::create([
        'reference'     => 'PROD-001',
        'label'         => 'Article',
        'family_id'     => $family->id,
        'selling_price' => 1000,
        'granularity'   => Granularity::Quantity,
    ]);

    $zero = StockLevel::create([
        'point_of_sale_id' => $pos->id,
        'product_id'       => $product->id,
        'quantity'         => 0,
    ]);

    $positive = StockLevel::create([
        'point_of_sale_id' => $pos->id,
        'product_id'       => null,
        'product_variant_id' => null,
        'quantity'         => 10,
    ]);

    expect($zero->isAvailable())->toBeFalse();
    expect($positive->isAvailable())->toBeTrue();

    tenancy()->end();
});

// ─────────────────────────────────────────────────────────────────────────────
// T5 — Service product must not have a stock level entry
// ─────────────────────────────────────────────────────────────────────────────

it('service_product_must_not_have_stock_level', function (): void {
    $tenant = Tenant::create(['name' => 'Service Stock Corp', 'status' => 'actif']);
    tenancy()->initialize($tenant);

    $unit    = OrganizationalUnit::create(['name' => 'Yaoundé']);
    $pos     = PointOfSale::create(['name' => 'Boutique', 'organizational_unit_id' => $unit->id]);
    $family  = Family::create(['name' => 'Services']);
    $service = Product::create([
        'reference'     => 'SRV-100',
        'label'         => 'Livraison',
        'family_id'     => $family->id,
        'selling_price' => 2000,
        'granularity'   => Granularity::Service,
    ]);

    expect($service->isService())->toBeTrue();

    $count = StockLevel::where('product_id', $service->id)->count();
    expect($count)->toBe(0);

    tenancy()->end();
});
