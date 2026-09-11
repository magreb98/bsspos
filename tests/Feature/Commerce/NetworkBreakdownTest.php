<?php

declare(strict_types=1);

/**
 * Feature tests for network product localisation — Task 5.4
 *
 * networkBreakdown(product) returns per-POS stock, in-transit quantity, and
 * network total. Only POS with non-zero stock appear in the result.
 *
 * Decisions:
 *   D1 — breakdown joins stock_movements with points_of_sale
 *   D2 — POS with SUM(quantity) = 0 are excluded
 *   D3 — in_transit = SUM of TransferLine quantities where status = in_transit
 *   D4 — total = sum(pos stocks) + in_transit (network invariant preserved)
 */

use App\Control\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Commerce\Internal\Enums\Granularity;
use Modules\Commerce\Internal\Enums\TransferStatus;
use Modules\Commerce\Internal\Models\Family;
use Modules\Commerce\Internal\Models\OrganizationalUnit;
use Modules\Commerce\Internal\Models\PointOfSale;
use Modules\Commerce\Internal\Models\Product;
use Modules\Commerce\Internal\Models\StockMovement;
use Modules\Commerce\Internal\Models\Transfer;
use Modules\Commerce\Internal\Services\NetworkStockService;
use Modules\Commerce\Internal\Services\TransferService;

uses(RefreshDatabase::class);

// ─────────────────────────────────────────────────────────────────────────────
// Helpers
// ─────────────────────────────────────────────────────────────────────────────

function makeBreakdownPos(string $name): PointOfSale
{
    $unit = OrganizationalUnit::create(['name' => "{$name} HQ", 'active' => true]);

    return PointOfSale::create(['name' => $name, 'organizational_unit_id' => $unit->id, 'active' => true]);
}

function makeBreakdownProduct(string $ref): Product
{
    $family = Family::firstOrCreate(['name' => 'Breakdown Family', 'active' => true]);

    return Product::create([
        'reference'     => $ref,
        'label'         => "Product {$ref}",
        'family_id'     => $family->id,
        'selling_price' => 5000,
        'vat_rate'      => '19.25',
        'granularity'   => Granularity::Quantity,
        'active'        => true,
    ]);
}

function addMovement(PointOfSale $pos, Product $product, int $qty): void
{
    StockMovement::create([
        'point_of_sale_id' => $pos->id,
        'product_id'       => $product->id,
        'sale_line_id'     => null,
        'quantity'         => $qty,
        'occurred_at'      => now(),
    ]);
}

// ─────────────────────────────────────────────────────────────────────────────
// B1 — No stock anywhere: empty breakdown
// ─────────────────────────────────────────────────────────────────────────────

it('breakdown_is_empty_when_no_stock', function (): void {
    $tenant = Tenant::create(['name' => 'Breakdown Corp A', 'status' => 'actif']);
    tenancy()->initialize($tenant);

    $product = makeBreakdownProduct('BRK-001');
    $svc     = new NetworkStockService();

    $result = $svc->networkBreakdown($product);

    expect($result['pos'])->toBeEmpty();
    expect($result['in_transit'])->toBe(0);
    expect($result['total'])->toBe(0);

    tenancy()->end();
});

// ─────────────────────────────────────────────────────────────────────────────
// B2 — Stock at two POS, no transfers: breakdown lists both
// ─────────────────────────────────────────────────────────────────────────────

it('breakdown_lists_pos_with_stock', function (): void {
    $tenant = Tenant::create(['name' => 'Breakdown Corp B', 'status' => 'actif']);
    tenancy()->initialize($tenant);

    $posA    = makeBreakdownPos('Alpha POS');
    $posB    = makeBreakdownPos('Beta POS');
    $product = makeBreakdownProduct('BRK-002');

    addMovement($posA, $product, 50);
    addMovement($posB, $product, 30);

    $svc    = new NetworkStockService();
    $result = $svc->networkBreakdown($product);

    expect($result['in_transit'])->toBe(0);
    expect($result['total'])->toBe(80);
    expect(count($result['pos']))->toBe(2);

    $names = array_column($result['pos'], 'pos_name');
    expect($names)->toContain('Alpha POS');
    expect($names)->toContain('Beta POS');

    $byName = array_combine($names, $result['pos']);
    expect($byName['Alpha POS']['stock'])->toBe(50);
    expect($byName['Beta POS']['stock'])->toBe(30);

    tenancy()->end();
});

// ─────────────────────────────────────────────────────────────────────────────
// B3 — POS with zero net stock is excluded from breakdown
// ─────────────────────────────────────────────────────────────────────────────

it('breakdown_excludes_pos_with_zero_net_stock', function (): void {
    $tenant = Tenant::create(['name' => 'Breakdown Corp C', 'status' => 'actif']);
    tenancy()->initialize($tenant);

    $posA    = makeBreakdownPos('POS With Stock');
    $posB    = makeBreakdownPos('POS Zero Stock');
    $product = makeBreakdownProduct('BRK-003');

    addMovement($posA, $product, 40);
    addMovement($posB, $product, 20);
    addMovement($posB, $product, -20); // net = 0

    $svc    = new NetworkStockService();
    $result = $svc->networkBreakdown($product);

    expect(count($result['pos']))->toBe(1);
    expect($result['pos'][0]['pos_name'])->toBe('POS With Stock');
    expect($result['pos'][0]['stock'])->toBe(40);
    expect($result['total'])->toBe(40);

    tenancy()->end();
});

// ─────────────────────────────────────────────────────────────────────────────
// B4 — In-transit quantity appears separately; network total is preserved
// ─────────────────────────────────────────────────────────────────────────────

it('breakdown_shows_in_transit_and_preserves_network_total', function (): void {
    $tenant = Tenant::create(['name' => 'Breakdown Corp D', 'status' => 'actif']);
    tenancy()->initialize($tenant);

    $source      = makeBreakdownPos('Warehouse D');
    $destination = makeBreakdownPos('Shop D');
    $product     = makeBreakdownProduct('BRK-004');

    addMovement($source, $product, 100);

    $svc     = new NetworkStockService();
    $before  = $svc->networkTotal($product);

    $transfer = Transfer::create([
        'source_pos_id'      => $source->id,
        'destination_pos_id' => $destination->id,
        'status'             => TransferStatus::Pending,
    ]);
    $transfer->lines()->create(['product_id' => $product->id, 'quantity' => 35]);

    (new TransferService())->dispatch($transfer);

    $result = $svc->networkBreakdown($product);

    expect($result['in_transit'])->toBe(35);
    expect($result['total'])->toBe($before);

    expect($svc->posStock($product, $source->id))->toBe(65);

    tenancy()->end();
});
