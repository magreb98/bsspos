<?php

declare(strict_types=1);

/**
 * Feature tests for inter-POS transfer — Task 5.1
 *
 * Decisions:
 *   D1 — transfers + transfer_lines tables
 *   D2 — TransferStatus enum: pending / in_transit / received / cancelled
 *   D3 — dispatch() creates negative StockMovement at source, status → in_transit
 *   D4 — receive() creates positive StockMovement at destination, status → received
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
use Modules\Commerce\Internal\Services\TransferService;

uses(RefreshDatabase::class);

// ─────────────────────────────────────────────────────────────────────────────
// Helpers
// ─────────────────────────────────────────────────────────────────────────────

function makeTransferPos(string $name): PointOfSale
{
    $unit = OrganizationalUnit::create(['name' => "{$name} HQ", 'active' => true]);

    return PointOfSale::create(['name' => $name, 'organizational_unit_id' => $unit->id, 'active' => true]);
}

function makeTransferProduct(string $ref, Granularity $granularity = Granularity::Quantity): Product
{
    $family = Family::firstOrCreate(['name' => 'Transfer Family', 'active' => true]);

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

function makePendingTransfer(PointOfSale $source, PointOfSale $destination): Transfer
{
    return Transfer::create([
        'source_pos_id'      => $source->id,
        'destination_pos_id' => $destination->id,
        'status'             => TransferStatus::Pending,
    ]);
}

// ─────────────────────────────────────────────────────────────────────────────
// T1 — Dispatch creates negative StockMovement at source; status → in_transit
// ─────────────────────────────────────────────────────────────────────────────

it('dispatch_creates_negative_movement_at_source_and_sets_in_transit', function (): void {
    $tenant = Tenant::create(['name' => 'Transfer Corp A', 'status' => 'actif']);
    tenancy()->initialize($tenant);

    $source      = makeTransferPos('Source POS');
    $destination = makeTransferPos('Destination POS');
    $product     = makeTransferProduct('TRF-001');

    $transfer = makePendingTransfer($source, $destination);
    $transfer->lines()->create(['product_id' => $product->id, 'quantity' => 15]);

    (new TransferService())->dispatch($transfer);

    $transfer->refresh();
    expect($transfer->status)->toBe(TransferStatus::InTransit);
    expect($transfer->dispatched_at)->not->toBeNull();

    $movement = StockMovement::where('product_id', $product->id)
        ->where('point_of_sale_id', $source->id)
        ->firstOrFail();

    expect($movement->quantity)->toBe(-15);

    tenancy()->end();
});

// ─────────────────────────────────────────────────────────────────────────────
// T2 — Receive creates positive StockMovement at destination; status → received
// ─────────────────────────────────────────────────────────────────────────────

it('receive_creates_positive_movement_at_destination_and_sets_received', function (): void {
    $tenant = Tenant::create(['name' => 'Transfer Corp B', 'status' => 'actif']);
    tenancy()->initialize($tenant);

    $source      = makeTransferPos('Source B');
    $destination = makeTransferPos('Destination B');
    $product     = makeTransferProduct('TRF-002');

    $transfer = makePendingTransfer($source, $destination);
    $transfer->lines()->create(['product_id' => $product->id, 'quantity' => 8]);

    $svc = new TransferService();
    $svc->dispatch($transfer);
    $transfer->refresh();
    $svc->receive($transfer);

    $transfer->refresh();
    expect($transfer->status)->toBe(TransferStatus::Received);
    expect($transfer->received_at)->not->toBeNull();

    $destMovement = StockMovement::where('product_id', $product->id)
        ->where('point_of_sale_id', $destination->id)
        ->firstOrFail();

    expect($destMovement->quantity)->toBe(8);

    tenancy()->end();
});

// ─────────────────────────────────────────────────────────────────────────────
// T3 — Dispatching an already in-transit transfer throws DomainException
// ─────────────────────────────────────────────────────────────────────────────

it('dispatching_in_transit_transfer_throws_domain_exception', function (): void {
    $tenant = Tenant::create(['name' => 'Transfer Corp C', 'status' => 'actif']);
    tenancy()->initialize($tenant);

    $source      = makeTransferPos('Source C');
    $destination = makeTransferPos('Destination C');
    $product     = makeTransferProduct('TRF-003');

    $transfer = makePendingTransfer($source, $destination);
    $transfer->lines()->create(['product_id' => $product->id, 'quantity' => 5]);

    $svc = new TransferService();
    $svc->dispatch($transfer);
    $transfer->refresh();

    expect(fn () => $svc->dispatch($transfer))
        ->toThrow(\DomainException::class, 'Only pending transfers can be dispatched.');

    tenancy()->end();
});

// ─────────────────────────────────────────────────────────────────────────────
// T4 — Service product creates no StockMovement on dispatch
// ─────────────────────────────────────────────────────────────────────────────

it('dispatch_service_product_creates_no_stock_movement', function (): void {
    $tenant = Tenant::create(['name' => 'Transfer Corp D', 'status' => 'actif']);
    tenancy()->initialize($tenant);

    $source      = makeTransferPos('Source D');
    $destination = makeTransferPos('Destination D');
    $product     = makeTransferProduct('TRF-SVC-001', Granularity::Service);

    $transfer = makePendingTransfer($source, $destination);
    $transfer->lines()->create(['product_id' => $product->id, 'quantity' => 3]);

    (new TransferService())->dispatch($transfer);

    expect(StockMovement::count())->toBe(0);

    tenancy()->end();
});
