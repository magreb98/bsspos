<?php

declare(strict_types=1);

/**
 * Feature tests for network stock invariant — Task 5.2
 *
 * Invariant: networkTotal(product) = SUM(stock_movements) + SUM(in-transit quantities)
 * This total is constant across dispatch and receive — only redistributed.
 *
 * Decisions:
 *   D1 — networkTotal = posStock + inTransit
 *   D2 — dispatch: posStock -= N, inTransit += N  → total unchanged
 *   D3 — receive:  posStock += N, inTransit -= N  → total unchanged
 *   D4 — Service products have no stock movements and are excluded
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

function makeNetworkPos(string $name): PointOfSale
{
    $unit = OrganizationalUnit::create(['name' => "{$name} HQ", 'active' => true]);

    return PointOfSale::create(['name' => $name, 'organizational_unit_id' => $unit->id, 'active' => true]);
}

function makeNetworkProduct(string $ref, Granularity $granularity = Granularity::Quantity): Product
{
    $family = Family::firstOrCreate(['name' => 'Network Family', 'active' => true]);

    return Product::create([
        'reference'     => $ref,
        'label'         => "Product {$ref}",
        'family_id'     => $family->id,
        'selling_price' => 5000,
        'vat_rate'      => '19.25',
        'granularity'   => $granularity,
        'active'        => true,
    ]);
}

function seedStock(PointOfSale $pos, Product $product, int $qty): void
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
// N1 — networkTotal is invariant across dispatch
// ─────────────────────────────────────────────────────────────────────────────

it('network_total_unchanged_after_dispatch', function (): void {
    $tenant = Tenant::create(['name' => 'Network Corp A', 'status' => 'actif']);
    tenancy()->initialize($tenant);

    $source      = makeNetworkPos('Net Source A');
    $destination = makeNetworkPos('Net Destination A');
    $product     = makeNetworkProduct('NET-001');

    seedStock($source, $product, 100);

    $svc     = new NetworkStockService();
    $before  = $svc->networkTotal($product);

    $transfer = Transfer::create([
        'source_pos_id'      => $source->id,
        'destination_pos_id' => $destination->id,
        'status'             => TransferStatus::Pending,
    ]);
    $transfer->lines()->create(['product_id' => $product->id, 'quantity' => 30]);

    (new TransferService())->dispatch($transfer);

    expect($svc->networkTotal($product))->toBe($before);
    expect($svc->posStock($product, $source->id))->toBe(70);

    tenancy()->end();
});

// ─────────────────────────────────────────────────────────────────────────────
// N2 — networkTotal is invariant across receive
// ─────────────────────────────────────────────────────────────────────────────

it('network_total_unchanged_after_receive', function (): void {
    $tenant = Tenant::create(['name' => 'Network Corp B', 'status' => 'actif']);
    tenancy()->initialize($tenant);

    $source      = makeNetworkPos('Net Source B');
    $destination = makeNetworkPos('Net Destination B');
    $product     = makeNetworkProduct('NET-002');

    seedStock($source, $product, 80);

    $svc = new NetworkStockService();

    $transfer = Transfer::create([
        'source_pos_id'      => $source->id,
        'destination_pos_id' => $destination->id,
        'status'             => TransferStatus::Pending,
    ]);
    $transfer->lines()->create(['product_id' => $product->id, 'quantity' => 20]);

    $tsvc = new TransferService();
    $tsvc->dispatch($transfer);
    $transfer->refresh();

    $afterDispatch = $svc->networkTotal($product);

    $tsvc->receive($transfer);

    expect($svc->networkTotal($product))->toBe($afterDispatch);
    expect($svc->posStock($product, $source->id))->toBe(60);
    expect($svc->posStock($product, $destination->id))->toBe(20);

    tenancy()->end();
});

// ─────────────────────────────────────────────────────────────────────────────
// N3 — networkTotal across multiple concurrent in-transit transfers
// ─────────────────────────────────────────────────────────────────────────────

it('network_total_correct_with_multiple_in_transit_transfers', function (): void {
    $tenant = Tenant::create(['name' => 'Network Corp C', 'status' => 'actif']);
    tenancy()->initialize($tenant);

    $warehouse = makeNetworkPos('Warehouse C');
    $shop1     = makeNetworkPos('Shop C1');
    $shop2     = makeNetworkPos('Shop C2');
    $product   = makeNetworkProduct('NET-003');

    seedStock($warehouse, $product, 200);

    $tsvc = new TransferService();
    $svc  = new NetworkStockService();

    $t1 = Transfer::create([
        'source_pos_id'      => $warehouse->id,
        'destination_pos_id' => $shop1->id,
        'status'             => TransferStatus::Pending,
    ]);
    $t1->lines()->create(['product_id' => $product->id, 'quantity' => 50]);

    $t2 = Transfer::create([
        'source_pos_id'      => $warehouse->id,
        'destination_pos_id' => $shop2->id,
        'status'             => TransferStatus::Pending,
    ]);
    $t2->lines()->create(['product_id' => $product->id, 'quantity' => 70]);

    $initial = $svc->networkTotal($product);

    $tsvc->dispatch($t1);
    expect($svc->networkTotal($product))->toBe($initial);

    $tsvc->dispatch($t2);
    expect($svc->networkTotal($product))->toBe($initial);

    $t1->refresh();
    $tsvc->receive($t1);
    expect($svc->networkTotal($product))->toBe($initial);

    $t2->refresh();
    $tsvc->receive($t2);
    expect($svc->networkTotal($product))->toBe($initial);

    expect($svc->posStock($product, $warehouse->id))->toBe(80);
    expect($svc->posStock($product, $shop1->id))->toBe(50);
    expect($svc->posStock($product, $shop2->id))->toBe(70);

    tenancy()->end();
});
