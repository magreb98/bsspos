<?php

declare(strict_types=1);

/**
 * HTTP endpoint tests for ProductImage file upload — Task 11.3
 *
 * Endpoint tested:
 *   POST /commerce/products/{product}/images/upload  (multipart)
 *
 * Decisions under test:
 *   PU1 — valid jpg → 201, data.url non null
 *   PU2 — valid webp → 201
 *   PU3 — invalid MIME (pdf) → 422
 *   PU4 — file too large (> 5 MB) → 422
 *   PU5 — unknown product → 404
 *   PU6 — unauthenticated → 401
 *
 * Phone range makePuTenant : +237600920001 → +237600920006
 */

use App\Control\Domaine;
use App\Control\Tenant;
use App\Platform\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Modules\Commerce\Internal\Enums\Granularity;
use Modules\Commerce\Internal\Models\Family;
use Modules\Commerce\Internal\Models\Product;

uses(RefreshDatabase::class);

// ─────────────────────────────────────────────────────────────────────────────
// Local helpers
// ─────────────────────────────────────────────────────────────────────────────

/**
 * @return array{0: Tenant, 1: User, 2: string}
 */
function makePuTenant(string $tag, string $phone): array
{
    $domain = "pu-{$tag}.bsspos.cm";

    $tenant = Tenant::create(['name' => "PU HTTP {$tag}", 'status' => 'actif']);
    Domaine::create(['tenant_id' => $tenant->id, 'domaine' => $domain]);

    tenancy()->initialize($tenant);

    seedTenantRolesAndPermissions();

    $user = User::create([
        'first_name' => 'PU',
        'last_name'  => 'Tester',
        'phone'      => $phone,
        'password'   => bcrypt('pass'),
        'active'     => true,
    ]);

    $user->assignRole('proprietaire');

    tenancy()->end();

    return [$tenant, $user, $domain];
}

function makePuProduct(Tenant $tenant, string $suffix): Product
{
    tenancy()->initialize($tenant);

    $family  = Family::create(['name' => "PU-Famille-{$suffix}"]);
    $product = Product::create([
        'reference'     => "PU-REF-{$suffix}",
        'label'         => "PU Produit {$suffix}",
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
// PU1 — valid jpg upload → 201, data.url non null
// ─────────────────────────────────────────────────────────────────────────────

it('pu1_upload_valid_jpg_returns_201', function (): void {
    Storage::fake('public');

    [$tenant, $user, $domain] = makePuTenant('pu1', '+237600920001');
    $product = makePuProduct($tenant, '001');

    $file = UploadedFile::fake()->image('galaxy-front.jpg');

    $response = $this->actingAs($user)
        ->withHeaders(['Host' => $domain])
        ->post("/commerce/products/{$product->id}/images/upload", ['image' => $file])
        ->assertStatus(201)
        ->assertJsonStructure(['data' => ['id', 'url', 'position']]);

    $url = $response->json('data.url');
    expect($url)->not->toBeNull()->and($url)->toBeString()->and(strlen($url))->toBeGreaterThan(0);
});

// ─────────────────────────────────────────────────────────────────────────────
// PU2 — valid webp upload → 201
// ─────────────────────────────────────────────────────────────────────────────

it('pu2_upload_valid_webp_returns_201', function (): void {
    Storage::fake('public');

    [$tenant, $user, $domain] = makePuTenant('pu2', '+237600920002');
    $product = makePuProduct($tenant, '002');

    $file = UploadedFile::fake()->image('galaxy-back.webp');

    $this->actingAs($user)
        ->withHeaders(['Host' => $domain])
        ->post("/commerce/products/{$product->id}/images/upload", ['image' => $file])
        ->assertStatus(201)
        ->assertJsonPath('data.position', 0);
});

// ─────────────────────────────────────────────────────────────────────────────
// PU3 — invalid MIME (pdf) → 422
// ─────────────────────────────────────────────────────────────────────────────

it('pu3_upload_invalid_mime_returns_422', function (): void {
    Storage::fake('public');

    [$tenant, $user, $domain] = makePuTenant('pu3', '+237600920003');
    $product = makePuProduct($tenant, '003');

    $file = UploadedFile::fake()->create('document.pdf', 100, 'application/pdf');

    $this->actingAs($user)
        ->withHeaders(['Host' => $domain, 'Accept' => 'application/json'])
        ->post("/commerce/products/{$product->id}/images/upload", ['image' => $file])
        ->assertStatus(422);
});

// ─────────────────────────────────────────────────────────────────────────────
// PU4 — file too large (> 5 MB) → 422
// ─────────────────────────────────────────────────────────────────────────────

it('pu4_upload_too_large_returns_422', function (): void {
    Storage::fake('public');

    [$tenant, $user, $domain] = makePuTenant('pu4', '+237600920004');
    $product = makePuProduct($tenant, '004');

    // 6000 KB = ~5.86 MB, exceeds 5120 KB limit
    $file = UploadedFile::fake()->create('big-image.jpg', 6000, 'image/jpeg');

    $this->actingAs($user)
        ->withHeaders(['Host' => $domain, 'Accept' => 'application/json'])
        ->post("/commerce/products/{$product->id}/images/upload", ['image' => $file])
        ->assertStatus(422);
});

// ─────────────────────────────────────────────────────────────────────────────
// PU5 — unknown product → 404
// ─────────────────────────────────────────────────────────────────────────────

it('pu5_upload_unknown_product_returns_404', function (): void {
    Storage::fake('public');

    [, $user, $domain] = makePuTenant('pu5', '+237600920005');

    $ghostId = '00000000-0000-0000-0000-000000000099';
    $file    = UploadedFile::fake()->image('photo.jpg');

    $this->actingAs($user)
        ->withHeaders(['Host' => $domain])
        ->post("/commerce/products/{$ghostId}/images/upload", ['image' => $file])
        ->assertStatus(404);
});

// ─────────────────────────────────────────────────────────────────────────────
// PU6 — unauthenticated → 401
// ─────────────────────────────────────────────────────────────────────────────

it('pu6_upload_unauthenticated_returns_401', function (): void {
    Storage::fake('public');

    [$tenant, , $domain] = makePuTenant('pu6', '+237600920006');
    $product = makePuProduct($tenant, '006');

    $file = UploadedFile::fake()->image('photo.jpg');

    $this->withHeaders(['Host' => $domain, 'Accept' => 'application/json'])
        ->post("/commerce/products/{$product->id}/images/upload", ['image' => $file])
        ->assertStatus(401);
});
