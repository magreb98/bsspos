<?php

declare(strict_types=1);

/**
 * HTTP endpoint tests — Commerce referential (families, products, customers, stock) — Lot 12
 *
 * Endpoints under test:
 *   GET  /commerce/families               → permission:family.list
 *   POST /commerce/families               → permission:family.write
 *   GET  /commerce/products               → permission:product.list
 *   POST /commerce/products               → permission:product.write
 *   GET  /commerce/customers              → permission:customer.list
 *   POST /commerce/customers              → permission:customer.write
 *   GET  /commerce/stock                  → permission:inventory.list
 *
 * Decisions under test:
 *   RA1 — GET families returns 200 with empty list
 *   RA2 — POST family creates and returns 201
 *   RA3 — POST family with invalid parent returns 404
 *   RA4 — GET products returns 200 with created product
 *   RA5 — POST product creates and returns 201 (integer price stored)
 *   RA6 — POST product with unknown family returns 404
 *   RA7 — GET customers returns 200
 *   RA8 — POST customer returns 201
 *   RA9 — GET stock returns 200 list
 *
 * Phone range makeRefTenant : +237601010001 → +237601010020
 */

use App\Control\Domaine;
use App\Control\Tenant;
use App\Platform\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Commerce\Internal\Enums\Granularity;
use Modules\Commerce\Internal\Models\Family;
use Modules\Commerce\Internal\Models\Product;

uses(RefreshDatabase::class);

// ─────────────────────────────────────────────────────────────────────────────
// Helpers
// ─────────────────────────────────────────────────────────────────────────────

/**
 * @return array{0: Tenant, 1: User, 2: string}
 */
function makeRefTenant(string $tag, string $phone): array
{
    $domain = "ref-{$tag}.bsspos.cm";

    $tenant = Tenant::create(['name' => "Ref HTTP {$tag}", 'status' => 'actif']);
    Domaine::create(['tenant_id' => $tenant->id, 'domaine' => $domain]);

    tenancy()->initialize($tenant);

    seedTenantRolesAndPermissions();

    $user = User::create([
        'first_name' => 'Ref',
        'last_name'  => 'Tester',
        'phone'      => $phone,
        'password'   => bcrypt('pass'),
        'active'     => true,
    ]);

    $user->assignRole('proprietaire');

    tenancy()->end();

    return [$tenant, $user, $domain];
}

// ─────────────────────────────────────────────────────────────────────────────
// RA1 — GET families returns 200 with empty list
// ─────────────────────────────────────────────────────────────────────────────

it('ra1_get_families_returns_200_empty', function (): void {
    [, $user, $domain] = makeRefTenant('ra1', '+237601010001');

    $this->actingAs($user)
        ->withHeaders(['Host' => $domain])
        ->getJson('/commerce/families')
        ->assertStatus(200)
        ->assertJsonPath('data', []);
});

// ─────────────────────────────────────────────────────────────────────────────
// RA2 — POST family creates and returns 201
// ─────────────────────────────────────────────────────────────────────────────

it('ra2_post_family_creates_201', function (): void {
    [, $user, $domain] = makeRefTenant('ra2', '+237601010002');

    $this->actingAs($user)
        ->withHeaders(['Host' => $domain])
        ->postJson('/commerce/families', ['name' => 'Téléphones Mobiles'])
        ->assertStatus(201)
        ->assertJsonPath('data.name', 'Téléphones Mobiles')
        ->assertJsonPath('data.active', true);
});

// ─────────────────────────────────────────────────────────────────────────────
// RA3 — POST family with unknown parent_id returns 404
// ─────────────────────────────────────────────────────────────────────────────

it('ra3_post_family_unknown_parent_returns_404', function (): void {
    [, $user, $domain] = makeRefTenant('ra3', '+237601010003');

    $ghostId = '00000000-0000-0000-0000-000000000099';

    $this->actingAs($user)
        ->withHeaders(['Host' => $domain])
        ->postJson('/commerce/families', ['name' => 'Sub-cat', 'parent_id' => $ghostId])
        ->assertStatus(404)
        ->assertJsonPath('code', 'PARENT_NOT_FOUND');
});

// ─────────────────────────────────────────────────────────────────────────────
// RA4 — GET products returns 200 with created product
// ─────────────────────────────────────────────────────────────────────────────

it('ra4_get_products_returns_200_with_list', function (): void {
    [$tenant, $user, $domain] = makeRefTenant('ra4', '+237601010004');

    tenancy()->initialize($tenant);
    $family  = Family::create(['name' => 'RA4-Famille']);
    Product::create([
        'reference'     => 'RA4-REF',
        'label'         => 'Produit RA4',
        'family_id'     => $family->id,
        'selling_price' => 50000,
        'vat_rate'      => '19.25',
        'granularity'   => Granularity::Quantity,
        'active'        => true,
    ]);
    tenancy()->end();

    $this->actingAs($user)
        ->withHeaders(['Host' => $domain])
        ->getJson('/commerce/products')
        ->assertStatus(200)
        ->assertJsonCount(1, 'data');
});

// ─────────────────────────────────────────────────────────────────────────────
// RA5 — POST product returns 201 with integer selling_price stored
// ─────────────────────────────────────────────────────────────────────────────

it('ra5_post_product_returns_201_price_is_integer', function (): void {
    [$tenant, $user, $domain] = makeRefTenant('ra5', '+237601010005');

    tenancy()->initialize($tenant);
    $family = Family::create(['name' => 'RA5-Famille']);
    tenancy()->end();

    $response = $this->actingAs($user)
        ->withHeaders(['Host' => $domain])
        ->postJson('/commerce/products', [
            'reference'     => 'RA5-REF',
            'label'         => 'Laptop Asus',
            'family_id'     => $family->id,
            'selling_price' => 350000,
            'vat_rate'      => 19.25,
            'granularity'   => 'quantity',
        ])
        ->assertStatus(201)
        ->assertJsonPath('data.label', 'Laptop Asus');

    expect($response->json('data.selling_price'))->toBeInt();
});

// ─────────────────────────────────────────────────────────────────────────────
// RA6 — POST product with unknown family returns 404
// ─────────────────────────────────────────────────────────────────────────────

it('ra6_post_product_unknown_family_returns_404', function (): void {
    [, $user, $domain] = makeRefTenant('ra6', '+237601010006');

    $ghostId = '00000000-0000-0000-0000-000000000098';

    $this->actingAs($user)
        ->withHeaders(['Host' => $domain])
        ->postJson('/commerce/products', [
            'reference'     => 'RA6-REF',
            'label'         => 'Ghost Product',
            'family_id'     => $ghostId,
            'selling_price' => 10000,
            'vat_rate'      => 0,
            'granularity'   => 'service',
        ])
        ->assertStatus(404)
        ->assertJsonPath('code', 'FAMILY_NOT_FOUND');
});

// ─────────────────────────────────────────────────────────────────────────────
// RA7 — GET customers returns 200 empty list
// ─────────────────────────────────────────────────────────────────────────────

it('ra7_get_customers_returns_200_empty', function (): void {
    [, $user, $domain] = makeRefTenant('ra7', '+237601010007');

    $this->actingAs($user)
        ->withHeaders(['Host' => $domain])
        ->getJson('/commerce/customers')
        ->assertStatus(200)
        ->assertJsonPath('data', []);
});

// ─────────────────────────────────────────────────────────────────────────────
// RA8 — POST customer returns 201
// ─────────────────────────────────────────────────────────────────────────────

it('ra8_post_customer_returns_201', function (): void {
    [, $user, $domain] = makeRefTenant('ra8', '+237601010008');

    $this->actingAs($user)
        ->withHeaders(['Host' => $domain])
        ->postJson('/commerce/customers', [
            'name'  => 'Jean-Pierre Mballa',
            'phone' => '+237699001122',
        ])
        ->assertStatus(201)
        ->assertJsonPath('data.name', 'Jean-Pierre Mballa')
        ->assertJsonPath('data.outstanding_balance', 0);
});

// ─────────────────────────────────────────────────────────────────────────────
// RA9 — GET stock returns 200
// ─────────────────────────────────────────────────────────────────────────────

it('ra9_get_stock_returns_200', function (): void {
    [, $user, $domain] = makeRefTenant('ra9', '+237601010009');

    $this->actingAs($user)
        ->withHeaders(['Host' => $domain])
        ->getJson('/commerce/stock')
        ->assertStatus(200)
        ->assertJsonPath('data', []);
});
