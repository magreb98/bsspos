<?php

declare(strict_types=1);

/**
 * HTTP endpoint tests for SerialUnit resource — Task 10.3
 *
 * These tests are intentionally RED: the controllers are stubs that return
 * ['data' => []] or ['data' => null] with 201/200.  They will turn GREEN once
 * the real implementation (controller, request, resource, routes) is in place.
 *
 * Decisions under test:
 *   SU1  — POST valid payload → 201, data.serial_number correct, data.status = 'available'
 *   SU2  — POST with product_id as non-UUID string → 422 VALIDATION_ERROR on field "product_id"
 *          (stub's bare `required` rule passes for any non-empty string, so stub returns 201;
 *           the real FormRequest adds a `uuid` rule which fails → 422 → genuinely RED)
 *   SU3  — POST with serial_number as integer (wrong type) → 422 VALIDATION_ERROR on field "serial_number"
 *          (stub's bare `required` rule passes for integers, so stub returns 201;
 *           the real FormRequest adds a `string` rule which fails → 422 → genuinely RED)
 *   SU4  — POST with non-existent product_id → 404 PRODUCT_NOT_FOUND
 *   SU5  — POST duplicate (same product_id + serial_number) → 409 SERIAL_NUMBER_DUPLICATE
 *   SU6  — GET /{id} existing → 200 with full field structure
 *   SU7  — GET /{id} missing → 404
 *   SU8  — GET / → 200 envelope { data: [...], meta: { current_page, per_page, total, last_page } }
 *   SU9  — GET /?product_id= → only serial units for product A, not product B
 *
 * Expected failure NOW (before implementation):
 *   - assertStatus(201) receives 200 (stub)           — SU1 fails at status or at assertJsonPath
 *   - assertStatus(422/409/404) receive 200 (stub)    — SU2, SU3, SU4, SU5, SU7 fail at status
 *   - assertJsonPath() fails because data is [] or null — SU1 fails at serial_number/status check
 *   - assertJsonStructure(['data', 'meta']) fails      — SU8 fails: no "meta" key in stub
 *   - count-based assertion fails on empty data        — SU9 fails at toHaveCount(1)
 *   - chained POST→GET (SU6) fails: POST returns stub data.id = null, GET structure check fails
 *
 * Phone range  : +237600840001 → +237600840020  (non-conflicting with other suites)
 * Tenant tags  : su1, su2, ...  (prefix 'su')
 *
 * Helper naming:
 *   makeSerialTenant()   — local, body identical to makeElectronicsHttpTenant / makeSpecEndpointTenant
 *                          Renamed to avoid fatal PHP redeclaration when the full suite runs.
 *   makeSerialEndpointProduct() — local, body identical to makeElectronicsProduct in DeviceSpecEndpointTest.
 *                          Renamed to avoid fatal PHP redeclaration when the full suite runs.
 */

use App\Control\Domaine;
use App\Control\Tenant;
use App\Platform\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Commerce\Internal\Enums\Granularity;
use Modules\Commerce\Internal\Models\Family;
use Modules\Commerce\Internal\Models\Product;
use Modules\SecteurElectronique\Enums\SerialStatus;
use Modules\SecteurElectronique\Models\SerialUnit;

uses(RefreshDatabase::class);

// ─────────────────────────────────────────────────────────────────────────────
// Local helpers
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Creates a tenant with its domain (landlord DB) then initialises the tenant
 * context to insert a user (tenant DB) before ending the context.
 *
 * Body is identical to makeElectronicsHttpTenant() in HttpInfrastructureTest.php
 * and makeSpecEndpointTenant() in DeviceSpecEndpointTest.php.
 * The name differs to avoid a fatal redeclaration error when all three files run
 * together in the full suite (Pest loads every file in the same PHP process).
 *
 * @return array{0: Tenant, 1: User, 2: string}
 */
function makeSerialTenant(string $tag, string $phone): array
{
    $domain = "serial-{$tag}.bsspos.cm";

    $tenant = Tenant::create(['name' => "SerialUnit HTTP {$tag}", 'status' => 'actif']);
    Domaine::create(['tenant_id' => $tenant->id, 'domaine' => $domain]);

    tenancy()->initialize($tenant);

    seedTenantRolesAndPermissions();

    $user = User::create([
        'first_name' => 'Serial',
        'last_name'  => 'Tester',
        'phone'      => $phone,
        'password'   => bcrypt('pass'),
        'active'     => true,
    ]);

    $user->assignRole('proprietaire');

    tenancy()->end();

    return [$tenant, $user, $domain];
}

/**
 * Initialises the tenant context, creates a Family + Product with Serial
 * granularity, ends the context, and returns the Product so callers can use
 * its $id in HTTP payloads.
 *
 * Body is identical to makeElectronicsProduct() in DeviceSpecEndpointTest.php.
 * Renamed to avoid a fatal redeclaration error when both files run together.
 */
function makeSerialEndpointProduct(Tenant $tenant, string $ref = 'SU-PROD-001'): Product
{
    tenancy()->initialize($tenant);

    $family = Family::create(['name' => "SerialFam-{$ref}", 'active' => true]);

    $product = Product::create([
        'reference'     => $ref,
        'label'         => 'Test Serial Device',
        'family_id'     => $family->id,
        'selling_price' => 120000,
        'vat_rate'      => '19.25',
        'granularity'   => Granularity::Serial,
        'active'        => true,
    ]);

    tenancy()->end();

    return $product;
}

// ─────────────────────────────────────────────────────────────────────────────
// SU1 — store with valid payload → 201, serial_number echoed, status = available
// ─────────────────────────────────────────────────────────────────────────────

it('su1_store_valid_payload_creates_serial_unit_with_available_status', function (): void {
    [$tenant, $user, $domain] = makeSerialTenant('su1', '+237600840001');
    $product = makeSerialEndpointProduct($tenant, 'SU-001');

    $this->actingAs($user)
        ->withHeaders(['Host' => $domain])
        ->postJson('/electronics/serial-units', [
            'product_id'    => $product->id,
            'serial_number' => 'SN-001',
        ])
        ->assertStatus(201)
        ->assertJsonPath('data.serial_number', 'SN-001')
        ->assertJsonPath('data.status', 'available');
});

// ─────────────────────────────────────────────────────────────────────────────
// SU2 — store with product_id as non-UUID string returns 422 VALIDATION_ERROR
//        (stub's `required` rule accepts any non-empty string → returns 201;
//         the real FormRequest adds a `uuid` rule which rejects this value → 422)
// ─────────────────────────────────────────────────────────────────────────────

it('su2_store_invalid_product_id_format_returns_422_validation_error', function (): void {
    [, $user, $domain] = makeSerialTenant('su2', '+237600840002');

    $this->actingAs($user)
        ->withHeaders(['Host' => $domain])
        ->postJson('/electronics/serial-units', [
            'product_id'    => 'not-a-valid-uuid',   // non-empty but not UUID format
            'serial_number' => 'SN-002',
        ])
        ->assertStatus(422)
        ->assertJsonPath('code', 'VALIDATION_ERROR')
        ->assertJsonPath('champ', 'product_id');
});

// ─────────────────────────────────────────────────────────────────────────────
// SU3 — store with serial_number as integer (wrong type) returns 422 VALIDATION_ERROR
//        (stub's `required` rule accepts any non-empty value including integers → returns 201;
//         the real FormRequest adds a `string` rule which rejects a numeric value → 422)
// ─────────────────────────────────────────────────────────────────────────────

it('su3_store_integer_serial_number_returns_422_validation_error', function (): void {
    [$tenant, $user, $domain] = makeSerialTenant('su3', '+237600840003');
    $product = makeSerialEndpointProduct($tenant, 'SU-003');

    $this->actingAs($user)
        ->withHeaders(['Host' => $domain])
        ->postJson('/electronics/serial-units', [
            'product_id'    => $product->id,
            'serial_number' => 12345,   // integer, not a string — must fail `string` rule
        ])
        ->assertStatus(422)
        ->assertJsonPath('code', 'VALIDATION_ERROR')
        ->assertJsonPath('champ', 'serial_number');
});

// ─────────────────────────────────────────────────────────────────────────────
// SU4 — store with a non-existent product_id returns 404 PRODUCT_NOT_FOUND
// ─────────────────────────────────────────────────────────────────────────────

it('su4_store_with_nonexistent_product_id_returns_404_product_not_found', function (): void {
    [, $user, $domain] = makeSerialTenant('su4', '+237600840004');

    $nonExistentProductId = '00000000-0000-0000-0000-000000000000';

    $this->actingAs($user)
        ->withHeaders(['Host' => $domain])
        ->postJson('/electronics/serial-units', [
            'product_id'    => $nonExistentProductId,
            'serial_number' => 'SN-004',
        ])
        ->assertStatus(404)
        ->assertJsonPath('code', 'PRODUCT_NOT_FOUND');
});

// ─────────────────────────────────────────────────────────────────────────────
// SU5 — store duplicate (same product_id + serial_number) returns 409
// ─────────────────────────────────────────────────────────────────────────────

it('su5_store_duplicate_serial_number_returns_409_serial_number_duplicate', function (): void {
    [$tenant, $user, $domain] = makeSerialTenant('su5', '+237600840005');
    $product = makeSerialEndpointProduct($tenant, 'SU-005');

    $payload = [
        'product_id'    => $product->id,
        'serial_number' => 'SN-005',
    ];

    // First creation — must succeed
    $this->actingAs($user)
        ->withHeaders(['Host' => $domain])
        ->postJson('/electronics/serial-units', $payload)
        ->assertStatus(201);

    // Second creation with same product_id + serial_number — must conflict
    $this->actingAs($user)
        ->withHeaders(['Host' => $domain])
        ->postJson('/electronics/serial-units', $payload)
        ->assertStatus(409)
        ->assertJsonPath('code', 'SERIAL_NUMBER_DUPLICATE');
});

// ─────────────────────────────────────────────────────────────────────────────
// SU6 — show returns the existing serial unit with all expected fields
// ─────────────────────────────────────────────────────────────────────────────

it('su6_show_returns_existing_serial_unit_with_all_fields', function (): void {
    [$tenant, $user, $domain] = makeSerialTenant('su6', '+237600840006');
    $product = makeSerialEndpointProduct($tenant, 'SU-006');

    // Create via POST first, capture the id from the response
    $createResponse = $this->actingAs($user)
        ->withHeaders(['Host' => $domain])
        ->postJson('/electronics/serial-units', [
            'product_id'    => $product->id,
            'serial_number' => 'SN-006',
        ])
        ->assertStatus(201);

    $serialUnitId = $createResponse->json('data.id');

    $this->actingAs($user)
        ->withHeaders(['Host' => $domain])
        ->getJson("/electronics/serial-units/{$serialUnitId}")
        ->assertStatus(200)
        ->assertJsonStructure([
            'data' => [
                'id', 'product_id', 'serial_number', 'status',
                'sale_line_id', 'created_at', 'updated_at',
            ],
        ])
        ->assertJsonPath('data.serial_number', 'SN-006')
        ->assertJsonPath('data.status', 'available');
});

// ─────────────────────────────────────────────────────────────────────────────
// SU7 — show returns 404 for a non-existent serial unit id
// ─────────────────────────────────────────────────────────────────────────────

it('su7_show_returns_404_for_nonexistent_serial_unit', function (): void {
    [, $user, $domain] = makeSerialTenant('su7', '+237600840007');

    $nonExistentId = '00000000-0000-0000-0000-000000000000';

    $this->actingAs($user)
        ->withHeaders(['Host' => $domain])
        ->getJson("/electronics/serial-units/{$nonExistentId}")
        ->assertStatus(404);
});

// ─────────────────────────────────────────────────────────────────────────────
// SU8 — index returns envelope with "data" (array) and "meta" pagination keys
// ─────────────────────────────────────────────────────────────────────────────

it('su8_index_returns_data_array_and_meta_pagination_envelope', function (): void {
    [, $user, $domain] = makeSerialTenant('su8', '+237600840008');

    $this->actingAs($user)
        ->withHeaders(['Host' => $domain])
        ->getJson('/electronics/serial-units')
        ->assertStatus(200)
        ->assertJsonStructure([
            'data',
            'meta' => ['current_page', 'per_page', 'total', 'last_page'],
        ]);
});

// ─────────────────────────────────────────────────────────────────────────────
// SU9 — index filtered by ?product_id= returns only the serial units of that product
// ─────────────────────────────────────────────────────────────────────────────

it('su9_index_filtered_by_product_id_returns_only_matching_serial_units', function (): void {
    [$tenant, $user, $domain] = makeSerialTenant('su9', '+237600840009');
    $productA = makeSerialEndpointProduct($tenant, 'SU-009A');
    $productB = makeSerialEndpointProduct($tenant, 'SU-009B');

    // Create a serial unit for product A
    $this->actingAs($user)
        ->withHeaders(['Host' => $domain])
        ->postJson('/electronics/serial-units', [
            'product_id'    => $productA->id,
            'serial_number' => 'SN-009A',
        ])
        ->assertStatus(201);

    // Create a serial unit for product B
    $this->actingAs($user)
        ->withHeaders(['Host' => $domain])
        ->postJson('/electronics/serial-units', [
            'product_id'    => $productB->id,
            'serial_number' => 'SN-009B',
        ])
        ->assertStatus(201);

    // Filter by product A — only one serial unit must be returned and it must belong to A
    $response = $this->actingAs($user)
        ->withHeaders(['Host' => $domain])
        ->getJson("/electronics/serial-units?product_id={$productA->id}")
        ->assertStatus(200)
        ->assertJsonStructure(['data', 'meta']);

    $data = $response->json('data');
    expect($data)->toHaveCount(1)
        ->and($data[0]['product_id'])->toBe($productA->id);
});

// ─────────────────────────────────────────────────────────────────────────────
// SU10 — DELETE /{id} available unit → 204 (Task 11.2)
// ─────────────────────────────────────────────────────────────────────────────

it('su10_destroy_available_serial_unit_returns_204', function (): void {
    [$tenant, $user, $domain] = makeSerialTenant('su10', '+237600840021');
    $product = makeSerialEndpointProduct($tenant, 'SU-010');

    $createResponse = $this->actingAs($user)
        ->withHeaders(['Host' => $domain])
        ->postJson('/electronics/serial-units', [
            'product_id'    => $product->id,
            'serial_number' => 'SN-010',
        ])
        ->assertStatus(201);

    $unitId = $createResponse->json('data.id');

    $this->actingAs($user)
        ->withHeaders(['Host' => $domain])
        ->deleteJson("/electronics/serial-units/{$unitId}")
        ->assertStatus(204);

    // Confirm it's gone
    $this->actingAs($user)
        ->withHeaders(['Host' => $domain])
        ->getJson("/electronics/serial-units/{$unitId}")
        ->assertStatus(404);
});

// ─────────────────────────────────────────────────────────────────────────────
// SU11 — DELETE sold unit → 409 SERIAL_UNIT_NOT_DELETABLE (Task 11.2)
// ─────────────────────────────────────────────────────────────────────────────

it('su11_destroy_sold_serial_unit_returns_409', function (): void {
    [$tenant, $user, $domain] = makeSerialTenant('su11', '+237600840022');
    $product = makeSerialEndpointProduct($tenant, 'SU-011');

    tenancy()->initialize($tenant);
    $unit = SerialUnit::create([
        'product_id'    => $product->id,
        'serial_number' => 'SN-011-SOLD',
        'status'        => SerialStatus::Sold,
    ]);
    $unitId = $unit->id;
    tenancy()->end();

    $this->actingAs($user)
        ->withHeaders(['Host' => $domain])
        ->deleteJson("/electronics/serial-units/{$unitId}")
        ->assertStatus(409)
        ->assertJsonPath('code', 'SERIAL_UNIT_NOT_DELETABLE')
        ->assertJsonPath('champ', null);
});

// ─────────────────────────────────────────────────────────────────────────────
// SU12 — DELETE non-existent → 404 (route model binding) (Task 11.2)
// ─────────────────────────────────────────────────────────────────────────────

it('su12_destroy_nonexistent_serial_unit_returns_404', function (): void {
    [, $user, $domain] = makeSerialTenant('su12', '+237600840023');

    $ghostId = '00000000-0000-0000-0000-000000000012';

    $this->actingAs($user)
        ->withHeaders(['Host' => $domain])
        ->deleteJson("/electronics/serial-units/{$ghostId}")
        ->assertStatus(404);
});
