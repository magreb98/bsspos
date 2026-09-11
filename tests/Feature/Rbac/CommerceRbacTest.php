<?php

declare(strict_types=1);

/**
 * RBAC tests — Commerce module (image endpoints) — Task 11.4
 *
 * Endpoints under test:
 *   GET    /commerce/products/{product}/images     → permission:image.list
 *   POST   /commerce/products/{product}/images     → permission:image.write
 *   POST   /commerce/products/{product}/images/upload → permission:image.write
 *
 * Decisions under test:
 *   RC1 — vendeur CAN list images (200)
 *   RC2 — vendeur CANNOT store image by URL (403)
 *   RC3 — gérant CAN store image by URL (201)
 *   RC4 — user with NO role CANNOT list images (403)
 *   RC5 — proprietaire CAN upload image (201)
 *
 * Phone range makeCrTenant : +237600930001 → +237600930009
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
// Helpers
// ─────────────────────────────────────────────────────────────────────────────

/**
 * @return array{0: Tenant, 1: User, 2: string}
 */
function makeCrTenant(string $tag, string $phone, string $role): array
{
    $domain = "cr-{$tag}.bsspos.cm";

    $tenant = Tenant::create(['name' => "CR RBAC {$tag}", 'status' => 'actif']);
    Domaine::create(['tenant_id' => $tenant->id, 'domaine' => $domain]);

    tenancy()->initialize($tenant);

    seedTenantRolesAndPermissions();

    $user = User::create([
        'first_name' => 'CR',
        'last_name'  => 'Tester',
        'phone'      => $phone,
        'password'   => bcrypt('pass'),
        'active'     => true,
    ]);

    if ($role !== '') {
        $user->assignRole($role);
    }

    tenancy()->end();

    return [$tenant, $user, $domain];
}

function makeCrProduct(Tenant $tenant, string $suffix): Product
{
    tenancy()->initialize($tenant);

    $family  = Family::create(['name' => "CR-Famille-{$suffix}"]);
    $product = Product::create([
        'reference'     => "CR-REF-{$suffix}",
        'label'         => "CR Produit {$suffix}",
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
// RC1 — vendeur CAN list images → 200
// ─────────────────────────────────────────────────────────────────────────────

it('rc1_vendeur_can_list_images', function (): void {
    [$tenant, $user, $domain] = makeCrTenant('rc1', '+237600930001', 'vendeur');
    $product = makeCrProduct($tenant, '001');

    $this->actingAs($user)
        ->withHeaders(['Host' => $domain])
        ->getJson("/commerce/products/{$product->id}/images")
        ->assertStatus(200)
        ->assertJsonPath('data', []);
});

// ─────────────────────────────────────────────────────────────────────────────
// RC2 — vendeur CANNOT store image by URL → 403
// ─────────────────────────────────────────────────────────────────────────────

it('rc2_vendeur_cannot_store_image_by_url', function (): void {
    [$tenant, $user, $domain] = makeCrTenant('rc2', '+237600930002', 'vendeur');
    $product = makeCrProduct($tenant, '002');

    $this->actingAs($user)
        ->withHeaders(['Host' => $domain])
        ->postJson("/commerce/products/{$product->id}/images", [
            'url' => 'uploads/test/photo.webp',
        ])
        ->assertStatus(403);
});

// ─────────────────────────────────────────────────────────────────────────────
// RC3 — gérant CAN store image by URL → 201
// ─────────────────────────────────────────────────────────────────────────────

it('rc3_gerant_can_store_image_by_url', function (): void {
    [$tenant, $user, $domain] = makeCrTenant('rc3', '+237600930003', 'gérant');
    $product = makeCrProduct($tenant, '003');

    $this->actingAs($user)
        ->withHeaders(['Host' => $domain])
        ->postJson("/commerce/products/{$product->id}/images", [
            'url' => 'uploads/test/photo.webp',
        ])
        ->assertStatus(201)
        ->assertJsonPath('data.position', 0);
});

// ─────────────────────────────────────────────────────────────────────────────
// RC4 — no-role user CANNOT list images → 403
// ─────────────────────────────────────────────────────────────────────────────

it('rc4_norole_user_cannot_list_images', function (): void {
    [$tenant, $user, $domain] = makeCrTenant('rc4', '+237600930004', '');
    $product = makeCrProduct($tenant, '004');

    $this->actingAs($user)
        ->withHeaders(['Host' => $domain])
        ->getJson("/commerce/products/{$product->id}/images")
        ->assertStatus(403);
});

// ─────────────────────────────────────────────────────────────────────────────
// RC5 — proprietaire CAN upload image → 201
// ─────────────────────────────────────────────────────────────────────────────

it('rc5_proprietaire_can_upload_image', function (): void {
    Storage::fake('public');

    [$tenant, $user, $domain] = makeCrTenant('rc5', '+237600930005', 'proprietaire');
    $product = makeCrProduct($tenant, '005');

    $file = UploadedFile::fake()->image('galaxy-front.jpg');

    $this->actingAs($user)
        ->withHeaders(['Host' => $domain])
        ->post("/commerce/products/{$product->id}/images/upload", ['image' => $file])
        ->assertStatus(201)
        ->assertJsonPath('data.position', 0);
});
