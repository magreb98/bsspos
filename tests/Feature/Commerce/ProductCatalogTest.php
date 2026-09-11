<?php

declare(strict_types=1);

/**
 * Feature tests for families and product catalog — Task 1.2
 *
 * Decisions:
 *   D1 — families: uuid PK, name, parent_id self-ref, active
 *   D2 — products: uuid PK, reference unique, label, family_id, selling_price bigint, vat_rate decimal, granularity, active
 *   D5 — vat_rate is a rate, not an amount; cast as decimal:2 string
 *   D7 — Product::isService() returns true for Granularity::Service
 */

use App\Control\Tenant;
use App\Platform\Money\Amount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Modules\Commerce\Internal\Enums\Granularity;
use Modules\Commerce\Internal\Models\Family;
use Modules\Commerce\Internal\Models\Product;

uses(RefreshDatabase::class);

// ─────────────────────────────────────────────────────────────────────────────
// T1 — families table has required columns
// ─────────────────────────────────────────────────────────────────────────────

it('families_table_has_required_columns', function (): void {
    $tenant = Tenant::create(['name' => 'Catalog Corp', 'status' => 'actif']);
    tenancy()->initialize($tenant);

    expect(Schema::hasTable('families'))->toBeTrue();
    expect(Schema::hasColumns('families', ['id', 'name', 'parent_id', 'active']))->toBeTrue();

    tenancy()->end();
});

// ─────────────────────────────────────────────────────────────────────────────
// T2 — products table has required columns
// ─────────────────────────────────────────────────────────────────────────────

it('products_table_has_required_columns', function (): void {
    $tenant = Tenant::create(['name' => 'Products Corp', 'status' => 'actif']);
    tenancy()->initialize($tenant);

    expect(Schema::hasTable('products'))->toBeTrue();
    expect(Schema::hasColumns('products', [
        'id', 'reference', 'label', 'family_id',
        'selling_price', 'vat_rate', 'granularity', 'active',
    ]))->toBeTrue();

    tenancy()->end();
});

// ─────────────────────────────────────────────────────────────────────────────
// T3 — Family self-referential relations work
// ─────────────────────────────────────────────────────────────────────────────

it('family_parent_and_children_relations_work', function (): void {
    $tenant = Tenant::create(['name' => 'Family Relations Corp', 'status' => 'actif']);
    tenancy()->initialize($tenant);

    $root  = Family::create(['name' => 'Électronique']);
    $child = Family::create(['name' => 'Téléphones', 'parent_id' => $root->id]);

    expect(($child->parent ?? throw new \DomainException('No parent.'))->id)->toBe($root->id);
    expect(($root->children->first() ?? throw new \DomainException('No child.'))->id)->toBe($child->id);

    tenancy()->end();
});

// ─────────────────────────────────────────────────────────────────────────────
// T4 — Product::family() returns the family
// ─────────────────────────────────────────────────────────────────────────────

it('product_belongs_to_family', function (): void {
    $tenant = Tenant::create(['name' => 'Product Family Corp', 'status' => 'actif']);
    tenancy()->initialize($tenant);

    $family  = Family::create(['name' => 'Boissons']);
    $product = Product::create([
        'reference'     => 'BOI-001',
        'label'         => 'Coca-Cola 50cl',
        'family_id'     => $family->id,
        'selling_price' => 500,
        'granularity'   => Granularity::Quantity,
    ]);

    expect(($product->family ?? throw new \DomainException('No family.'))->id)->toBe($family->id);

    tenancy()->end();
});

// ─────────────────────────────────────────────────────────────────────────────
// T6 — Product::isService() returns true for Granularity::Service
// ─────────────────────────────────────────────────────────────────────────────

it('product_is_service_returns_true_for_service_granularity', function (): void {
    $tenant = Tenant::create(['name' => 'Service Corp', 'status' => 'actif']);
    tenancy()->initialize($tenant);

    $family  = Family::create(['name' => 'Services']);
    $service = Product::create([
        'reference'     => 'SRV-001',
        'label'         => 'Installation',
        'family_id'     => $family->id,
        'selling_price' => 5000,
        'granularity'   => Granularity::Service,
    ]);

    $physical = Product::create([
        'reference'     => 'PHY-001',
        'label'         => 'Câble USB',
        'family_id'     => $family->id,
        'selling_price' => 1000,
        'granularity'   => Granularity::Quantity,
    ]);

    expect($service->isService())->toBeTrue();
    expect($physical->isService())->toBeFalse();

    tenancy()->end();
});

// ─────────────────────────────────────────────────────────────────────────────
// T7 — Deactivated product remains readable
// ─────────────────────────────────────────────────────────────────────────────

it('deactivated_product_remains_readable', function (): void {
    $tenant = Tenant::create(['name' => 'History Corp', 'status' => 'actif']);
    tenancy()->initialize($tenant);

    $family  = Family::create(['name' => 'Divers']);
    $product = Product::create([
        'reference'     => 'OLD-001',
        'label'         => 'Ancien produit',
        'family_id'     => $family->id,
        'selling_price' => 1000,
        'granularity'   => Granularity::Quantity,
        'active'        => false,
    ]);

    $found = Product::findOrFail($product->id);

    expect($found->active)->toBeFalse();
    expect($found->label)->toBe('Ancien produit');

    tenancy()->end();
});

// ─────────────────────────────────────────────────────────────────────────────
// T8 — selling_price returns an Amount object
// ─────────────────────────────────────────────────────────────────────────────

it('product_selling_price_returns_amount_object', function (): void {
    $tenant = Tenant::create(['name' => 'Amount Corp', 'status' => 'actif']);
    tenancy()->initialize($tenant);

    $family  = Family::create(['name' => 'Test']);
    $product = Product::create([
        'reference'     => 'AMT-001',
        'label'         => 'Produit test',
        'family_id'     => $family->id,
        'selling_price' => 15000,
        'granularity'   => Granularity::Quantity,
    ]);

    $fresh = Product::findOrFail($product->id);

    expect($fresh->selling_price)->toBeInstanceOf(Amount::class);
    expect($fresh->selling_price?->toInt())->toBe(15000);

    tenancy()->end();
});
