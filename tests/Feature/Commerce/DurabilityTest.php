<?php

declare(strict_types=1);

/**
 * Feature tests for offline queue durability — Task 3.3
 *
 * Decisions:
 *   D1 — Interrupted replay uses idempotency (existing mechanism)
 *   D2 — General exceptions in one sale do not block the rest of the batch
 *   D3 — OfflineSyncResult.failed counts sales that could not be processed
 */

use App\Control\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Commerce\Internal\Enums\SessionState;
use Modules\Commerce\Internal\Models\CashRegister;
use Modules\Commerce\Internal\Models\CashSession;
use Modules\Commerce\Internal\Models\Family;
use Modules\Commerce\Internal\Models\OrganizationalUnit;
use Modules\Commerce\Internal\Models\PointOfSale;
use Modules\Commerce\Internal\Models\Product;
use Modules\Commerce\Internal\Models\Sale;
use Modules\Commerce\Internal\Services\OfflineSyncService;
use Modules\Commerce\Internal\Sync\OfflineSaleRequest;
use Ramsey\Uuid\Uuid;

uses(RefreshDatabase::class);

// ─────────────────────────────────────────────────────────────────────────────
// Helpers
// ─────────────────────────────────────────────────────────────────────────────

function makeDurabilitySession(): CashSession
{
    $unit     = OrganizationalUnit::create(['name' => 'Durability HQ', 'active' => true]);
    $pos      = PointOfSale::create(['name' => 'Durability POS', 'organizational_unit_id' => $unit->id, 'active' => true]);
    $register = CashRegister::create(['name' => 'Durability R1', 'point_of_sale_id' => $pos->id, 'active' => true]);

    return CashSession::create([
        'cash_register_id' => $register->id,
        'state'            => SessionState::Open,
        'opened_at'        => now(),
        'opening_balance'  => 0,
        'opened_by'        => Uuid::uuid7()->toString(),
    ]);
}

function makeDurabilityProduct(string $ref): Product
{
    $family = Family::firstOrCreate(['name' => 'Durability Family', 'active' => true]);

    return Product::create([
        'reference'     => $ref,
        'label'         => "Product {$ref}",
        'family_id'     => $family->id,
        'selling_price' => 5000,
        'vat_rate'      => '19.25',
        'granularity'   => \Modules\Commerce\Internal\Enums\Granularity::Service,
        'active'        => true,
    ]);
}

/**
 * @param list<string> $keys
 * @return list<OfflineSaleRequest>
 */
function buildRequests(string $sessionId, string $productId, array $keys): array
{
    return array_map(
        fn (string $key) => new OfflineSaleRequest(
            idempotencyKey: $key,
            cashSessionId: $sessionId,
            lines: [[
                'product_id'               => $productId,
                'quantity'                 => 1,
                'designation'              => 'Item',
                'unit_price'               => 5000,
                'vat_rate'                 => '19.25',
                'line_total_excluding_tax' => 4192,
                'line_total_tax'           => 808,
                'line_total_including_tax' => 5000,
            ]],
        ),
        $keys
    );
}

// ─────────────────────────────────────────────────────────────────────────────
// T1 — Partial sync + full replay produces correct counts
// ─────────────────────────────────────────────────────────────────────────────

it('partial_sync_then_full_replay_produces_correct_counts', function (): void {
    $tenant = Tenant::create(['name' => 'Dur Corp A', 'status' => 'actif']);
    tenancy()->initialize($tenant);

    $session = makeDurabilitySession();
    $product = makeDurabilityProduct('DUR-001');

    $allKeys = ['dev:1', 'dev:2', 'dev:3', 'dev:4', 'dev:5'];

    // First call: only first 3 reach the server
    $firstBatch  = buildRequests($session->id, $product->id, array_slice($allKeys, 0, 3));
    $firstResult = (new OfflineSyncService())->sync($firstBatch);

    expect($firstResult->processed)->toBe(3);
    expect($firstResult->replayed)->toBe(0);
    expect($firstResult->failed)->toBe(0);

    // Second call: full replay of all 5
    $fullBatch    = buildRequests($session->id, $product->id, $allKeys);
    $secondResult = (new OfflineSyncService())->sync($fullBatch);

    expect($secondResult->processed)->toBe(2);
    expect($secondResult->replayed)->toBe(3);
    expect($secondResult->failed)->toBe(0);

    expect(Sale::count())->toBe(5);

    tenancy()->end();
});

// ─────────────────────────────────────────────────────────────────────────────
// T2 — A failed sale does not block the rest of the batch
// ─────────────────────────────────────────────────────────────────────────────

it('failed_sale_does_not_block_rest_of_batch', function (): void {
    $tenant = Tenant::create(['name' => 'Dur Corp B', 'status' => 'actif']);
    tenancy()->initialize($tenant);

    $session = makeDurabilitySession();
    $product = makeDurabilityProduct('DUR-002');

    $nonExistentSessionId = Uuid::uuid7()->toString();

    $requests = [
        new OfflineSaleRequest(
            idempotencyKey: 'dev:good-1',
            cashSessionId:  $session->id,
            lines: [[
                'product_id'               => $product->id,
                'quantity'                 => 1,
                'designation'              => 'Item',
                'unit_price'               => 5000,
                'vat_rate'                 => '19.25',
                'line_total_excluding_tax' => 4192,
                'line_total_tax'           => 808,
                'line_total_including_tax' => 5000,
            ]],
        ),
        new OfflineSaleRequest(
            idempotencyKey: 'dev:bad',
            cashSessionId:  $nonExistentSessionId,  // does not exist → confirm will fail
            lines: [[
                'product_id'               => $product->id,
                'quantity'                 => 1,
                'designation'              => 'Item',
                'unit_price'               => 5000,
                'vat_rate'                 => '19.25',
                'line_total_excluding_tax' => 4192,
                'line_total_tax'           => 808,
                'line_total_including_tax' => 5000,
            ]],
        ),
        new OfflineSaleRequest(
            idempotencyKey: 'dev:good-2',
            cashSessionId:  $session->id,
            lines: [[
                'product_id'               => $product->id,
                'quantity'                 => 1,
                'designation'              => 'Item',
                'unit_price'               => 5000,
                'vat_rate'                 => '19.25',
                'line_total_excluding_tax' => 4192,
                'line_total_tax'           => 808,
                'line_total_including_tax' => 5000,
            ]],
        ),
    ];

    $result = (new OfflineSyncService())->sync($requests);

    expect($result->processed)->toBe(2);
    expect($result->replayed)->toBe(0);
    expect($result->failed)->toBe(1);

    // The two good sales are confirmed; no orphaned draft from the bad sale
    expect(Sale::count())->toBe(2);

    tenancy()->end();
});

// ─────────────────────────────────────────────────────────────────────────────
// T3 — Empty batch returns all zeros
// ─────────────────────────────────────────────────────────────────────────────

it('empty_batch_returns_all_zeros', function (): void {
    $tenant = Tenant::create(['name' => 'Dur Corp C', 'status' => 'actif']);
    tenancy()->initialize($tenant);

    $result = (new OfflineSyncService())->sync([]);

    expect($result->processed)->toBe(0);
    expect($result->replayed)->toBe(0);
    expect($result->anomalies)->toBe(0);
    expect($result->failed)->toBe(0);

    tenancy()->end();
});
