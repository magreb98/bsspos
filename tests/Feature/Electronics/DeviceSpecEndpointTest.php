<?php

declare(strict_types=1);

/**
 * HTTP endpoint tests for DeviceSpec resource — Task 10.2
 *
 * These tests are intentionally RED: the controllers are stubs that return
 * ['data' => []] or ['data' => null].  They will turn GREEN once the real
 * implementation (controller, request, resource, routes) is in place.
 *
 * Decisions under test:
 *   DS1  — POST valid payload → 201 with data.brand / data.category / data.imei_required
 *   DS2  — POST without product_id → 422 VALIDATION_ERROR on field "product_id"
 *   DS3  — POST with category outside DeviceCategory enum → 422 on field "category"
 *   DS4  — POST when spec already exists → 409 DEVICE_SPEC_ALREADY_EXISTS
 *   DS5  — POST with optional technical fields → 201, all fields present in data
 *   DS6  — GET /{id} existing → 200 with full field structure
 *   DS7  — GET /{id} missing  → 404
 *   DS8  — GET /  → 200 envelope { data: [...], meta: {...} }
 *   DS9  — GET /?product_id=  → only the matching spec returned
 *   DS10 — PATCH /{id}        → updated ram_gb / storage_gb, product_id unchanged
 *   DS11 — POST tablet without imei_required → imei_required auto-deduced as true
 *
 * Expected failure NOW (before implementation):
 *   - assertStatus(201) receives 200 (stub)
 *   - assertStatus(422/409/404) receive 200 (stub)
 *   - assertJsonPath() fails because data is [] or null
 *   - assertJsonStructure(['data', 'meta']) fails — no "meta" key in stub
 *
 * Phone range : +237600830001 → +237600830020  (non-conflicting with other suites)
 * makeSpecEndpointTenant()    : declared locally below (same body as makeElectronicsHttpTenant in
 *                               HttpInfrastructureTest.php, renamed to avoid redeclaration when both
 *                               files run together in the full suite)
 * makeElectronicProduct()     : declared globally in DeviceSpecTest.php        — NOT redeclared here
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
// Local helpers
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Creates a tenant with its domain (landlord DB) then initialises the tenant
 * context to insert a user (tenant DB) before ending the context.
 *
 * Body is identical to makeSpecEndpointTenant() in HttpInfrastructureTest.php.
 * The name differs to avoid a fatal redeclaration error when both files run
 * together in the full suite (Pest loads every file in the same PHP process).
 *
 * @return array{0: Tenant, 1: User, 2: string}
 */
function makeSpecEndpointTenant(string $tag, string $phone): array
{
    $domain = "elec-{$tag}.bsspos.cm";

    $tenant = Tenant::create(['name' => "Electronics HTTP {$tag}", 'status' => 'actif']);
    Domaine::create(['tenant_id' => $tenant->id, 'domaine' => $domain]);

    tenancy()->initialize($tenant);

    seedTenantRolesAndPermissions();

    $user = User::create([
        'first_name' => 'Elec',
        'last_name'  => 'Tester',
        'phone'      => $phone,
        'password'   => bcrypt('pass'),
        'active'     => true,
    ]);

    $user->assignRole('proprietaire');

    tenancy()->end();

    return [$tenant, $user, $domain];
}

// Name "makeElectronicsProduct" (with 's') is distinct from the global
// "makeElectronicProduct" (without 's') in Feature/Commerce/DeviceSpecTest.php.
// Family name is derived from $ref so two calls in the same test never collide.

/**
 * Initialises the tenant context, creates a Family + Product, ends the
 * context, and returns the Product so callers can use its $id in HTTP payloads.
 */
function makeElectronicsProduct(Tenant $tenant, string $ref = 'DS-SPEC-001'): Product
{
    tenancy()->initialize($tenant);

    $family = Family::create(['name' => "Elec-{$ref}", 'active' => true]);

    $product = Product::create([
        'reference'     => $ref,
        'label'         => 'Test Device',
        'family_id'     => $family->id,
        'selling_price' => 150000,
        'vat_rate'      => '19.25',
        'granularity'   => Granularity::Serial,
        'active'        => true,
    ]);

    tenancy()->end();

    return $product;
}

// ─────────────────────────────────────────────────────────────────────────────
// DS1 — store with valid minimal payload creates the device spec
// ─────────────────────────────────────────────────────────────────────────────

it('ds1_store_valid_payload_creates_device_spec', function (): void {
    [$tenant, $user, $domain] = makeSpecEndpointTenant('ds1', '+237600830001');
    $product = makeElectronicsProduct($tenant, 'DS-001');

    $this->actingAs($user)
        ->withHeaders(['Host' => $domain])
        ->postJson('/electronics/device-specs', [
            'product_id'      => $product->id,
            'category'        => 'smartphone',
            'brand'           => 'Samsung',
            'model'           => 'Galaxy A54',
            'warranty_months' => 12,
        ])
        ->assertStatus(201)
        ->assertJsonPath('data.brand', 'Samsung')
        ->assertJsonPath('data.category', 'smartphone')
        ->assertJsonPath('data.imei_required', true); // auto-deduced: smartphone requiresImei
});

// ─────────────────────────────────────────────────────────────────────────────
// DS2 — store without product_id returns 422 VALIDATION_ERROR
// ─────────────────────────────────────────────────────────────────────────────

it('ds2_store_without_product_id_returns_422_validation_error', function (): void {
    [, $user, $domain] = makeSpecEndpointTenant('ds2', '+237600830002');

    $this->actingAs($user)
        ->withHeaders(['Host' => $domain])
        ->postJson('/electronics/device-specs', [
            'category'        => 'smartphone',
            'brand'           => 'Samsung',
            'model'           => 'Galaxy A54',
            'warranty_months' => 12,
        ])
        ->assertStatus(422)
        ->assertJsonPath('code', 'VALIDATION_ERROR')
        ->assertJsonPath('champ', 'product_id');
});

// ─────────────────────────────────────────────────────────────────────────────
// DS3 — store with a category value outside DeviceCategory enum returns 422
// ─────────────────────────────────────────────────────────────────────────────

it('ds3_store_with_invalid_category_returns_422', function (): void {
    [$tenant, $user, $domain] = makeSpecEndpointTenant('ds3', '+237600830003');
    $product = makeElectronicsProduct($tenant, 'DS-003');

    $this->actingAs($user)
        ->withHeaders(['Host' => $domain])
        ->postJson('/electronics/device-specs', [
            'product_id'      => $product->id,
            'category'        => 'fridge',   // not a DeviceCategory value
            'brand'           => 'Samsung',
            'model'           => 'Galaxy',
            'warranty_months' => 12,
        ])
        ->assertStatus(422)
        ->assertJsonPath('code', 'VALIDATION_ERROR')
        ->assertJsonPath('champ', 'category');
});

// ─────────────────────────────────────────────────────────────────────────────
// DS4 — store when spec already exists for that product returns 409
// ─────────────────────────────────────────────────────────────────────────────

it('ds4_store_duplicate_spec_returns_409_device_spec_already_exists', function (): void {
    [$tenant, $user, $domain] = makeSpecEndpointTenant('ds4', '+237600830004');
    $product = makeElectronicsProduct($tenant, 'DS-004');

    $payload = [
        'product_id'      => $product->id,
        'category'        => 'smartphone',
        'brand'           => 'Apple',
        'model'           => 'iPhone 15',
        'warranty_months' => 12,
    ];

    // First creation — must succeed
    $this->actingAs($user)
        ->withHeaders(['Host' => $domain])
        ->postJson('/electronics/device-specs', $payload)
        ->assertStatus(201);

    // Second creation for the same product — must conflict
    $this->actingAs($user)
        ->withHeaders(['Host' => $domain])
        ->postJson('/electronics/device-specs', $payload)
        ->assertStatus(409)
        ->assertJsonPath('code', 'DEVICE_SPEC_ALREADY_EXISTS');
});

// ─────────────────────────────────────────────────────────────────────────────
// DS5 — store with optional technical fields → all fields present in response
// ─────────────────────────────────────────────────────────────────────────────

it('ds5_store_with_optional_fields_persists_all_technical_attributes', function (): void {
    [$tenant, $user, $domain] = makeSpecEndpointTenant('ds5', '+237600830005');
    $product = makeElectronicsProduct($tenant, 'DS-005');

    $this->actingAs($user)
        ->withHeaders(['Host' => $domain])
        ->postJson('/electronics/device-specs', [
            'product_id'         => $product->id,
            'category'           => 'laptop',
            'brand'              => 'HP',
            'model'              => 'Pavilion 15',
            'warranty_months'    => 24,
            'screen_size_inches' => 15.6,
            'ram_gb'             => 16,
            'storage_gb'         => 512,
            'battery_mah'        => 3600,
            'operating_system'   => 'Windows 11',
        ])
        ->assertStatus(201)
        ->assertJsonPath('data.screen_size_inches', 15.6)
        ->assertJsonPath('data.ram_gb', 16)
        ->assertJsonPath('data.storage_gb', 512)
        ->assertJsonPath('data.battery_mah', 3600)
        ->assertJsonPath('data.operating_system', 'Windows 11');
});

// ─────────────────────────────────────────────────────────────────────────────
// DS6 — show returns the existing spec with all expected fields
// ─────────────────────────────────────────────────────────────────────────────

it('ds6_show_returns_existing_spec_with_all_fields', function (): void {
    [$tenant, $user, $domain] = makeSpecEndpointTenant('ds6', '+237600830006');
    $product = makeElectronicsProduct($tenant, 'DS-006');

    // Create via POST first, capture the id from the response
    $createResponse = $this->actingAs($user)
        ->withHeaders(['Host' => $domain])
        ->postJson('/electronics/device-specs', [
            'product_id'      => $product->id,
            'category'        => 'tablet',
            'brand'           => 'Samsung',
            'model'           => 'Galaxy Tab S9',
            'warranty_months' => 12,
            'ram_gb'          => 8,
        ])
        ->assertStatus(201);

    $specId = $createResponse->json('data.id');

    $this->actingAs($user)
        ->withHeaders(['Host' => $domain])
        ->getJson("/electronics/device-specs/{$specId}")
        ->assertStatus(200)
        ->assertJsonStructure([
            'data' => [
                'id', 'product_id', 'category', 'brand', 'model',
                'imei_required', 'warranty_months', 'created_at', 'updated_at',
            ],
        ])
        ->assertJsonPath('data.brand', 'Samsung')
        ->assertJsonPath('data.category', 'tablet')
        ->assertJsonPath('data.ram_gb', 8);
});

// ─────────────────────────────────────────────────────────────────────────────
// DS7 — show returns 404 for a non-existent spec id
// ─────────────────────────────────────────────────────────────────────────────

it('ds7_show_returns_404_for_nonexistent_spec', function (): void {
    [, $user, $domain] = makeSpecEndpointTenant('ds7', '+237600830007');

    $nonExistentId = '00000000-0000-0000-0000-000000000000';

    $this->actingAs($user)
        ->withHeaders(['Host' => $domain])
        ->getJson("/electronics/device-specs/{$nonExistentId}")
        ->assertStatus(404);
});

// ─────────────────────────────────────────────────────────────────────────────
// DS8 — index returns envelope with top-level "data" (array) and "meta" keys
// ─────────────────────────────────────────────────────────────────────────────

it('ds8_index_returns_data_array_and_meta_envelope', function (): void {
    [, $user, $domain] = makeSpecEndpointTenant('ds8', '+237600830008');

    $this->actingAs($user)
        ->withHeaders(['Host' => $domain])
        ->getJson('/electronics/device-specs')
        ->assertStatus(200)
        ->assertJsonStructure(['data', 'meta']);
});

// ─────────────────────────────────────────────────────────────────────────────
// DS9 — index filtered by ?product_id= returns only the spec for that product
// ─────────────────────────────────────────────────────────────────────────────

it('ds9_index_filtered_by_product_id_returns_only_matching_spec', function (): void {
    [$tenant, $user, $domain] = makeSpecEndpointTenant('ds9', '+237600830009');
    $productA = makeElectronicsProduct($tenant, 'DS-009A');
    $productB = makeElectronicsProduct($tenant, 'DS-009B');

    // Create spec for product A
    $this->actingAs($user)
        ->withHeaders(['Host' => $domain])
        ->postJson('/electronics/device-specs', [
            'product_id'      => $productA->id,
            'category'        => 'audio',
            'brand'           => 'Sony',
            'model'           => 'WH-1000XM5',
            'warranty_months' => 12,
        ])
        ->assertStatus(201);

    // Create spec for product B
    $this->actingAs($user)
        ->withHeaders(['Host' => $domain])
        ->postJson('/electronics/device-specs', [
            'product_id'      => $productB->id,
            'category'        => 'accessory',
            'brand'           => 'Anker',
            'model'           => 'PowerCore 20000',
            'warranty_months' => 6,
        ])
        ->assertStatus(201);

    // Filter by product A — only one spec must be returned
    $response = $this->actingAs($user)
        ->withHeaders(['Host' => $domain])
        ->getJson("/electronics/device-specs?product_id={$productA->id}")
        ->assertStatus(200)
        ->assertJsonStructure(['data', 'meta']);

    $data = $response->json('data');
    expect($data)->toHaveCount(1)
        ->and($data[0]['product_id'])->toBe($productA->id);
});

// ─────────────────────────────────────────────────────────────────────────────
// DS10 — update modifies ram_gb / storage_gb without touching product_id
// ─────────────────────────────────────────────────────────────────────────────

it('ds10_update_modifies_fields_without_changing_product_id', function (): void {
    [$tenant, $user, $domain] = makeSpecEndpointTenant('ds10', '+237600830010');
    $product = makeElectronicsProduct($tenant, 'DS-010');

    $createResponse = $this->actingAs($user)
        ->withHeaders(['Host' => $domain])
        ->postJson('/electronics/device-specs', [
            'product_id'      => $product->id,
            'category'        => 'laptop',
            'brand'           => 'Dell',
            'model'           => 'XPS 15',
            'warranty_months' => 24,
            'ram_gb'          => 8,
            'storage_gb'      => 256,
        ])
        ->assertStatus(201);

    $specId = $createResponse->json('data.id');

    $this->actingAs($user)
        ->withHeaders(['Host' => $domain])
        ->patchJson("/electronics/device-specs/{$specId}", [
            'ram_gb'     => 32,
            'storage_gb' => 1024,
        ])
        ->assertStatus(200)
        ->assertJsonPath('data.ram_gb', 32)
        ->assertJsonPath('data.storage_gb', 1024)
        ->assertJsonPath('data.product_id', $product->id); // must be unchanged
});

// ─────────────────────────────────────────────────────────────────────────────
// DS11 — imei_required is automatically true for Tablet when not supplied
// ─────────────────────────────────────────────────────────────────────────────

it('ds11_imei_required_auto_deduced_true_for_tablet', function (): void {
    [$tenant, $user, $domain] = makeSpecEndpointTenant('ds11', '+237600830011');
    $product = makeElectronicsProduct($tenant, 'DS-011');

    $this->actingAs($user)
        ->withHeaders(['Host' => $domain])
        ->postJson('/electronics/device-specs', [
            'product_id'      => $product->id,
            'category'        => 'tablet',
            'brand'           => 'Apple',
            'model'           => 'iPad Pro 12.9',
            'warranty_months' => 12,
            // imei_required intentionally omitted — must be deduced from DeviceCategory::Tablet
        ])
        ->assertStatus(201)
        ->assertJsonPath('data.category', 'tablet')
        ->assertJsonPath('data.imei_required', true);
});

// ─────────────────────────────────────────────────────────────────────────────
// DS12 — DELETE /{id} existing spec → 204 No Content (Task 11.2)
// ─────────────────────────────────────────────────────────────────────────────

it('ds12_destroy_existing_spec_returns_204', function (): void {
    [$tenant, $user, $domain] = makeSpecEndpointTenant('ds12', '+237600830012');
    $product = makeElectronicsProduct($tenant, 'DS-012');

    $createResponse = $this->actingAs($user)
        ->withHeaders(['Host' => $domain])
        ->postJson('/electronics/device-specs', [
            'product_id'      => $product->id,
            'category'        => 'laptop',
            'brand'           => 'Dell',
            'model'           => 'XPS 15',
            'warranty_months' => 24,
        ])
        ->assertStatus(201);

    $specId = $createResponse->json('data.id');

    $this->actingAs($user)
        ->withHeaders(['Host' => $domain])
        ->deleteJson("/electronics/device-specs/{$specId}")
        ->assertStatus(204);

    // Confirm it's gone
    $this->actingAs($user)
        ->withHeaders(['Host' => $domain])
        ->getJson("/electronics/device-specs/{$specId}")
        ->assertStatus(404);
});

// ─────────────────────────────────────────────────────────────────────────────
// DS13 — DELETE /{id_inexistant} → 404 (route model binding) (Task 11.2)
// ─────────────────────────────────────────────────────────────────────────────

it('ds13_destroy_nonexistent_spec_returns_404', function (): void {
    [, $user, $domain] = makeSpecEndpointTenant('ds13', '+237600830013');

    $ghostId = '00000000-0000-0000-0000-000000000013';

    $this->actingAs($user)
        ->withHeaders(['Host' => $domain])
        ->deleteJson("/electronics/device-specs/{$ghostId}")
        ->assertStatus(404);
});
