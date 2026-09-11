<?php

declare(strict_types=1);

/**
 * Feature tests for device specification — electronic sector
 *
 * Decisions:
 *   D1 — DeviceSpec: one per product, holds typed technical attributes
 *   D2 — DeviceCategory enum drives IMEI requirement default
 *   D3 — DeviceSpecService::attach() enforces one-spec-per-product
 *   D4 — additional_specs jsonb stores category-specific extras
 */

use App\Control\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Commerce\Internal\Enums\Granularity;
use Modules\Commerce\Internal\Models\Family;
use Modules\Commerce\Internal\Models\Product;
use Modules\SecteurElectronique\Enums\DeviceCategory;
use Modules\SecteurElectronique\Models\DeviceSpec;
use Modules\SecteurElectronique\Services\DeviceSpecService;

uses(RefreshDatabase::class);

// ─────────────────────────────────────────────────────────────────────────────
// Helpers
// ─────────────────────────────────────────────────────────────────────────────

function makeElectronicProduct(string $ref = 'ELEC-SPEC-001'): Product
{
    $family = Family::create(['name' => 'Electronics', 'active' => true]);

    return Product::create([
        'reference'     => $ref,
        'label'         => 'Smartphone',
        'family_id'     => $family->id,
        'selling_price' => 150000,
        'vat_rate'      => '19.25',
        'granularity'   => Granularity::Serial,
        'active'        => true,
    ]);
}

// ─────────────────────────────────────────────────────────────────────────────
// S1 — attach() creates a device spec linked to the product
// ─────────────────────────────────────────────────────────────────────────────

it('attach_creates_device_spec_for_product', function (): void {
    $tenant = Tenant::create(['name' => 'Spec Corp A', 'status' => 'actif']);
    tenancy()->initialize($tenant);

    $product = makeElectronicProduct();

    $svc  = new DeviceSpecService();
    $spec = $svc->attach($product, [
        'category'         => DeviceCategory::Smartphone,
        'brand'            => 'Samsung',
        'model'            => 'Galaxy A54',
        'color'            => 'Noir',
        'model_year'       => 2023,
        'imei_required'    => true,
        'warranty_months'  => 12,
        'screen_size_inches' => 6.4,
        'screen_resolution'  => '1080x2340',
        'panel_type'         => 'Super AMOLED',
        'processor'          => 'Exynos 1380',
        'ram_gb'             => 8,
        'storage_gb'         => 256,
        'bluetooth'          => true,
        'wifi'               => true,
        'nfc'                => true,
        'cellular_network'   => '4G',
        'battery_mah'        => 5000,
        'main_camera_mp'     => 50,
        'operating_system'   => 'Android 13',
        'weight_grams'       => 202,
    ]);

    expect($spec->product_id)->toBe($product->id)
        ->and($spec->brand)->toBe('Samsung')
        ->and($spec->category)->toBe(DeviceCategory::Smartphone)
        ->and($spec->ram_gb)->toBe(8)
        ->and($spec->storage_gb)->toBe(256)
        ->and($spec->battery_mah)->toBe(5000)
        ->and($spec->nfc)->toBeTrue();

    tenancy()->end();
});

// ─────────────────────────────────────────────────────────────────────────────
// S2 — attach() throws DomainException when product already has a spec
// ─────────────────────────────────────────────────────────────────────────────

it('attach_throws_when_product_already_has_spec', function (): void {
    $tenant = Tenant::create(['name' => 'Spec Corp B', 'status' => 'actif']);
    tenancy()->initialize($tenant);

    $product = makeElectronicProduct('ELEC-SPEC-002');
    $svc     = new DeviceSpecService();

    $svc->attach($product, [
        'category'        => DeviceCategory::Smartphone,
        'brand'           => 'Xiaomi',
        'warranty_months' => 12,
    ]);

    expect(fn () => $svc->attach($product, [
        'category'        => DeviceCategory::Smartphone,
        'brand'           => 'Samsung',
        'warranty_months' => 12,
    ]))->toThrow(\DomainException::class);

    tenancy()->end();
});

// ─────────────────────────────────────────────────────────────────────────────
// S3 — update() modifies individual fields without losing others
// ─────────────────────────────────────────────────────────────────────────────

it('update_modifies_spec_fields', function (): void {
    $tenant = Tenant::create(['name' => 'Spec Corp C', 'status' => 'actif']);
    tenancy()->initialize($tenant);

    $product = makeElectronicProduct('ELEC-SPEC-003');
    $svc     = new DeviceSpecService();

    $spec = $svc->attach($product, [
        'category'        => DeviceCategory::Laptop,
        'brand'           => 'HP',
        'model'           => 'Pavilion 15',
        'ram_gb'          => 8,
        'storage_gb'      => 512,
        'warranty_months' => 12,
    ]);

    $updated = $svc->update($spec, ['ram_gb' => 16, 'storage_gb' => 1024]);

    expect($updated->ram_gb)->toBe(16)
        ->and($updated->storage_gb)->toBe(1024)
        ->and($updated->brand)->toBe('HP');

    tenancy()->end();
});

// ─────────────────────────────────────────────────────────────────────────────
// S4 — forProduct() returns null when no spec exists
// ─────────────────────────────────────────────────────────────────────────────

it('for_product_returns_null_when_no_spec', function (): void {
    $tenant = Tenant::create(['name' => 'Spec Corp D', 'status' => 'actif']);
    tenancy()->initialize($tenant);

    $product = makeElectronicProduct('ELEC-SPEC-004');
    $svc     = new DeviceSpecService();

    expect($svc->forProduct($product))->toBeNull();

    tenancy()->end();
});

// ─────────────────────────────────────────────────────────────────────────────
// S5 — additional_specs jsonb stores arbitrary category-specific data
// ─────────────────────────────────────────────────────────────────────────────

it('additional_specs_stores_arbitrary_json', function (): void {
    $tenant = Tenant::create(['name' => 'Spec Corp E', 'status' => 'actif']);
    tenancy()->initialize($tenant);

    $product = makeElectronicProduct('ELEC-SPEC-005');
    $svc     = new DeviceSpecService();

    $extras = [
        'hdmi_ports'     => 2,
        'usb_ports'      => 3,
        'smart_tv'       => true,
        'hdr_support'    => 'HDR10+',
        'refresh_rate_hz' => 120,
    ];

    $spec = $svc->attach($product, [
        'category'           => DeviceCategory::Tv,
        'brand'              => 'Samsung',
        'model'              => 'Crystal 43"',
        'screen_size_inches' => 43.0,
        'screen_resolution'  => '3840x2160',
        'warranty_months'    => 24,
        'additional_specs'   => $extras,
    ]);

    expect($spec->additional_specs)->toBe($extras);

    $stored = $spec->additional_specs;
    assert(is_array($stored));
    expect($stored['smart_tv'])->toBeTrue()
        ->and($stored['refresh_rate_hz'])->toBe(120);

    tenancy()->end();
});

// ─────────────────────────────────────────────────────────────────────────────
// S6 — DeviceCategory::requiresImei() returns true only for phone/tablet
// ─────────────────────────────────────────────────────────────────────────────

it('device_category_requires_imei_for_phone_and_tablet_only', function (): void {
    expect(DeviceCategory::Smartphone->requiresImei())->toBeTrue()
        ->and(DeviceCategory::Tablet->requiresImei())->toBeTrue()
        ->and(DeviceCategory::Laptop->requiresImei())->toBeFalse()
        ->and(DeviceCategory::Tv->requiresImei())->toBeFalse()
        ->and(DeviceCategory::Audio->requiresImei())->toBeFalse()
        ->and(DeviceCategory::Accessory->requiresImei())->toBeFalse();
});
