<?php

declare(strict_types=1);

/**
 * Feature tests for the public catalog reader — Task 9.2
 *
 * Decisions:
 *   R1 — list() returns all projections from catalog_projections
 *   R2 — find(reference) returns the correct projection
 */

use App\Control\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Modules\Commerce\Public\CatalogProjection;
use Modules\Commerce\Public\Enums\Availability;
use Modules\Commerce\Public\PublicCatalogReader;
use Ramsey\Uuid\Uuid;

uses(RefreshDatabase::class);

// ─────────────────────────────────────────────────────────────────────────────
// Helpers
// ─────────────────────────────────────────────────────────────────────────────

function makeCatalogProjection(string $reference, Availability $availability = Availability::Available): CatalogProjection
{
    return CatalogProjection::create([
        'product_id' => Uuid::uuid7()->toString(),
        'reference' => $reference,
        'label' => "Product {$reference}",
        'selling_price' => 10000,
        'availability' => $availability,
        'updated_at' => now(),
    ]);
}

// ─────────────────────────────────────────────────────────────────────────────
// R1 — list returns all projections from catalog_projections
// ─────────────────────────────────────────────────────────────────────────────

it('list_returns_all_projections_from_catalog_projections', function (): void {
    $tenant = Tenant::create(['name' => 'Reader Corp R1', 'status' => 'actif']);
    tenancy()->initialize($tenant);

    Cache::flush();

    makeCatalogProjection('REF-R1-001');
    makeCatalogProjection('REF-R1-002');

    $reader = new PublicCatalogReader();
    $list = $reader->list();

    expect($list)->toHaveCount(2);
    $refs = $list->pluck('reference')->toArray();
    expect($refs)->toContain('REF-R1-001');
    expect($refs)->toContain('REF-R1-002');

    tenancy()->end();
});

// ─────────────────────────────────────────────────────────────────────────────
// R2 — find by reference returns correct projection
// ─────────────────────────────────────────────────────────────────────────────

it('find_by_reference_returns_correct_projection', function (): void {
    $tenant = Tenant::create(['name' => 'Reader Corp R2', 'status' => 'actif']);
    tenancy()->initialize($tenant);

    Cache::flush();

    makeCatalogProjection('REF-R2-TARGET', Availability::LowStock);
    makeCatalogProjection('REF-R2-OTHER');

    $reader = new PublicCatalogReader();
    $found = $reader->find('REF-R2-TARGET');

    expect($found)->not->toBeNull();
    expect($found?->reference)->toBe('REF-R2-TARGET');
    expect($found?->availability)->toBe(Availability::LowStock);

    $notFound = $reader->find('REF-NONEXISTENT');
    expect($notFound)->toBeNull();

    tenancy()->end();
});
