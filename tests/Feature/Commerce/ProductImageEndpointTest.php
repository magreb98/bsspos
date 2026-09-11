<?php

declare(strict_types=1);

/**
 * HTTP endpoint tests for ProductImage resource — Task 11.1
 *
 * Endpoints tested:
 *   GET    /commerce/products/{product}/images
 *   POST   /commerce/products/{product}/images
 *   POST   /commerce/products/{product}/images/reorder
 *   DELETE /commerce/products/{product}/images/{image}
 *
 * Decisions under test:
 *   PI1 — GET list returns 200 with empty data array when no images
 *   PI2 — POST with url only → 201, position auto-set to 0 (first image)
 *   PI3 — POST with explicit position → 201, position honoured
 *   PI4 — POST second image without position → 201, position = 1 (after first at 0)
 *   PI5 — POST on unknown product → 404 (route model binding)
 *   PI6 — POST missing url → 422 validation error
 *   PI7 — DELETE existing image → 204
 *   PI8 — DELETE image that belongs to another product → 404 IMAGE_NOT_FOUND
 *   PI9 — POST reorder with valid order array → 200, data reflects new positions
 *
 * Phone range makeProductImageTenant : +237600910001 → +237600910009
 * Tenant tags : pi1, pi2, …
 */

use App\Control\Domaine;
use App\Control\Tenant;
use App\Platform\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Commerce\Internal\Enums\Granularity;
use Modules\Commerce\Internal\Models\Family;
use Modules\Commerce\Internal\Models\Product;
use Modules\Commerce\Internal\Models\ProductImage;

uses(RefreshDatabase::class);

// ─────────────────────────────────────────────────────────────────────────────
// Local helpers
// ─────────────────────────────────────────────────────────────────────────────

/**
 * @return array{0: Tenant, 1: User, 2: string}
 */
function makeProductImageTenant(string $tag, string $phone): array
{
    $domain = "pi-{$tag}.bsspos.cm";

    $tenant = Tenant::create(['name' => "PI HTTP {$tag}", 'status' => 'actif']);
    Domaine::create(['tenant_id' => $tenant->id, 'domaine' => $domain]);

    tenancy()->initialize($tenant);

    seedTenantRolesAndPermissions();

    $user = User::create([
        'first_name' => 'PI',
        'last_name'  => 'Tester',
        'phone'      => $phone,
        'password'   => bcrypt('pass'),
        'active'     => true,
    ]);

    $user->assignRole('proprietaire');

    tenancy()->end();

    return [$tenant, $user, $domain];
}

function makePiProduct(Tenant $tenant, string $suffix): Product
{
    tenancy()->initialize($tenant);

    $family  = Family::create(['name' => "PI-Famille-{$suffix}"]);
    $product = Product::create([
        'reference'     => "PI-REF-{$suffix}",
        'label'         => "PI Produit {$suffix}",
        'family_id'     => $family->id,
        'selling_price' => 100000,
        'vat_rate'      => 19.25,
        'granularity'   => Granularity::Quantity,
        'active'        => true,
    ]);

    tenancy()->end();

    return $product;
}

// ─────────────────────────────────────────────────────────────────────────────
// PI1 — GET list returns 200 with empty data array when no images
// ─────────────────────────────────────────────────────────────────────────────

it('pi1_list_images_returns_200_empty_when_no_images', function (): void {
    [$tenant, $user, $domain] = makeProductImageTenant('pi1', '+237600910001');
    $product = makePiProduct($tenant, '001');

    $this->actingAs($user)
        ->withHeaders(['Host' => $domain])
        ->getJson("/commerce/products/{$product->id}/images")
        ->assertStatus(200)
        ->assertJsonPath('data', []);
});

// ─────────────────────────────────────────────────────────────────────────────
// PI2 — POST with url only → 201, position auto-set to 0
// ─────────────────────────────────────────────────────────────────────────────

it('pi2_store_first_image_auto_position_zero', function (): void {
    [$tenant, $user, $domain] = makeProductImageTenant('pi2', '+237600910002');
    $product = makePiProduct($tenant, '002');

    $this->actingAs($user)
        ->withHeaders(['Host' => $domain])
        ->postJson("/commerce/products/{$product->id}/images", [
            'url' => 'uploads/products/galaxy-a54/front.webp',
        ])
        ->assertStatus(201)
        ->assertJsonPath('data.url', 'uploads/products/galaxy-a54/front.webp')
        ->assertJsonPath('data.position', 0);
});

// ─────────────────────────────────────────────────────────────────────────────
// PI3 — POST with explicit position → 201, position honoured
// ─────────────────────────────────────────────────────────────────────────────

it('pi3_store_image_with_explicit_position', function (): void {
    [$tenant, $user, $domain] = makeProductImageTenant('pi3', '+237600910003');
    $product = makePiProduct($tenant, '003');

    $this->actingAs($user)
        ->withHeaders(['Host' => $domain])
        ->postJson("/commerce/products/{$product->id}/images", [
            'url'      => 'uploads/products/galaxy-a54/back.webp',
            'position' => 5,
        ])
        ->assertStatus(201)
        ->assertJsonPath('data.position', 5);
});

// ─────────────────────────────────────────────────────────────────────────────
// PI4 — POST second image without position → position = max + 1
// ─────────────────────────────────────────────────────────────────────────────

it('pi4_store_second_image_auto_position_increments', function (): void {
    [$tenant, $user, $domain] = makeProductImageTenant('pi4', '+237600910004');
    $product = makePiProduct($tenant, '004');

    // First image: position 0 (auto)
    $this->actingAs($user)
        ->withHeaders(['Host' => $domain])
        ->postJson("/commerce/products/{$product->id}/images", [
            'url' => 'uploads/pi4/front.webp',
        ])
        ->assertStatus(201)
        ->assertJsonPath('data.position', 0);

    // Second image: position should be 1 (auto = 0 + 1)
    $this->actingAs($user)
        ->withHeaders(['Host' => $domain])
        ->postJson("/commerce/products/{$product->id}/images", [
            'url' => 'uploads/pi4/back.webp',
        ])
        ->assertStatus(201)
        ->assertJsonPath('data.position', 1);
});

// ─────────────────────────────────────────────────────────────────────────────
// PI5 — POST on unknown product → 404 (route model binding)
// ─────────────────────────────────────────────────────────────────────────────

it('pi5_store_image_unknown_product_returns_404', function (): void {
    [$tenant, $user, $domain] = makeProductImageTenant('pi5', '+237600910005');
    makePiProduct($tenant, '005');

    $ghostId = '00000000-0000-0000-0000-000000000099';

    $this->actingAs($user)
        ->withHeaders(['Host' => $domain])
        ->postJson("/commerce/products/{$ghostId}/images", [
            'url' => 'uploads/ghost.webp',
        ])
        ->assertStatus(404);
});

// ─────────────────────────────────────────────────────────────────────────────
// PI6 — POST missing url → 422 validation error
// ─────────────────────────────────────────────────────────────────────────────

it('pi6_store_image_missing_url_returns_422', function (): void {
    [$tenant, $user, $domain] = makeProductImageTenant('pi6', '+237600910006');
    $product = makePiProduct($tenant, '006');

    $this->actingAs($user)
        ->withHeaders(['Host' => $domain])
        ->postJson("/commerce/products/{$product->id}/images", [])
        ->assertStatus(422);
});

// ─────────────────────────────────────────────────────────────────────────────
// PI7 — DELETE existing image → 204
// ─────────────────────────────────────────────────────────────────────────────

it('pi7_delete_existing_image_returns_204', function (): void {
    [$tenant, $user, $domain] = makeProductImageTenant('pi7', '+237600910007');
    $product = makePiProduct($tenant, '007');

    // Create image
    $response = $this->actingAs($user)
        ->withHeaders(['Host' => $domain])
        ->postJson("/commerce/products/{$product->id}/images", [
            'url' => 'uploads/pi7/front.webp',
        ])
        ->assertStatus(201);

    $imageId = $response->json('data.id');

    $this->actingAs($user)
        ->withHeaders(['Host' => $domain])
        ->deleteJson("/commerce/products/{$product->id}/images/{$imageId}")
        ->assertStatus(204);
});

// ─────────────────────────────────────────────────────────────────────────────
// PI8 — DELETE image belonging to another product → 404 IMAGE_NOT_FOUND
// ─────────────────────────────────────────────────────────────────────────────

it('pi8_delete_cross_product_image_returns_404', function (): void {
    [$tenant, $user, $domain] = makeProductImageTenant('pi8', '+237600910008');
    $productA = makePiProduct($tenant, '008A');
    $productB = makePiProduct($tenant, '008B');

    // Create image under product B
    $response = $this->actingAs($user)
        ->withHeaders(['Host' => $domain])
        ->postJson("/commerce/products/{$productB->id}/images", [
            'url' => 'uploads/pi8/photo.webp',
        ])
        ->assertStatus(201);

    $imageId = $response->json('data.id');

    // Try to delete it via product A's route
    $this->actingAs($user)
        ->withHeaders(['Host' => $domain])
        ->deleteJson("/commerce/products/{$productA->id}/images/{$imageId}")
        ->assertStatus(404)
        ->assertJsonPath('code', 'IMAGE_NOT_FOUND');
});

// ─────────────────────────────────────────────────────────────────────────────
// PI9 — POST reorder → 200, positions updated in order of the array
// ─────────────────────────────────────────────────────────────────────────────

it('pi9_reorder_images_updates_positions', function (): void {
    [$tenant, $user, $domain] = makeProductImageTenant('pi9', '+237600910009');
    $product = makePiProduct($tenant, '009');

    $idA = $this->actingAs($user)
        ->withHeaders(['Host' => $domain])
        ->postJson("/commerce/products/{$product->id}/images", ['url' => 'a.webp'])
        ->assertStatus(201)
        ->json('data.id');

    $idB = $this->actingAs($user)
        ->withHeaders(['Host' => $domain])
        ->postJson("/commerce/products/{$product->id}/images", ['url' => 'b.webp'])
        ->assertStatus(201)
        ->json('data.id');

    // Reverse: B first (position 0), A second (position 1)
    $this->actingAs($user)
        ->withHeaders(['Host' => $domain])
        ->postJson("/commerce/products/{$product->id}/images/reorder", [
            'order' => [$idB, $idA],
        ])
        ->assertStatus(200)
        ->assertJsonPath('data.0.id', $idB)
        ->assertJsonPath('data.0.position', 0)
        ->assertJsonPath('data.1.id', $idA)
        ->assertJsonPath('data.1.position', 1);
});
