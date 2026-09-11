<?php

declare(strict_types=1);

/**
 * Feature tests for offline sync and replay idempotence — Task 3.1
 *
 * Decisions:
 *   D1 — OfflineSaleRequest value object
 *   D2 — OfflineSyncResult value object
 *   D3 — OfflineSyncService::sync()
 *   D4 — Idempotence via unique idempotency_key on sales
 *   D5 — Arbitrary list size (volume)
 */

use App\Control\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Commerce\Internal\Enums\Granularity;
use Modules\Commerce\Internal\Enums\SessionState;
use Modules\Commerce\Internal\Models\CashRegister;
use Modules\Commerce\Internal\Models\CashSession;
use Modules\Commerce\Internal\Models\Family;
use Modules\Commerce\Internal\Models\OrganizationalUnit;
use Modules\Commerce\Internal\Models\PointOfSale;
use Modules\Commerce\Internal\Models\Product;
use Modules\Commerce\Internal\Models\Sale;
use Modules\Commerce\Internal\Sync\OfflineSaleRequest;
use Modules\Commerce\Internal\Services\OfflineSyncService;
use Ramsey\Uuid\Uuid;

uses(RefreshDatabase::class);

// ─────────────────────────────────────────────────────────────────────────────
// Helpers
// ─────────────────────────────────────────────────────────────────────────────

function makeSessionForSync(): CashSession
{
    $unit     = OrganizationalUnit::create(['name' => 'Sync HQ', 'active' => true]);
    $pos      = PointOfSale::create(['name' => 'Sync POS', 'organizational_unit_id' => $unit->id, 'active' => true]);
    $register = CashRegister::create(['name' => 'Sync R1', 'point_of_sale_id' => $pos->id, 'active' => true]);

    return CashSession::create([
        'cash_register_id' => $register->id,
        'state'            => SessionState::Open,
        'opened_at'        => now(),
        'opening_balance'  => 0,
        'opened_by'        => Uuid::uuid7()->toString(),
    ]);
}

function makeServiceProduct(string $ref): Product
{
    $family = Family::firstOrCreate(['name' => 'Sync Family', 'active' => true]);

    return Product::create([
        'reference'     => $ref,
        'label'         => "Product {$ref}",
        'family_id'     => $family->id,
        'selling_price' => 5000,
        'vat_rate'      => '19.25',
        'granularity'   => Granularity::Service,
        'active'        => true,
    ]);
}

function buildRequest(string $key, string $sessionId, string $productId): OfflineSaleRequest
{
    return new OfflineSaleRequest(
        idempotencyKey: $key,
        cashSessionId:  $sessionId,
        lines: [[
            'product_id'               => $productId,
            'quantity'                 => 1,
            'designation'              => 'Produit test',
            'unit_price'               => 5000,
            'vat_rate'                 => '19.25',
            'line_total_excluding_tax' => 4191,
            'line_total_tax'           => 809,
            'line_total_including_tax' => 5000,
        ]],
    );
}

// ─────────────────────────────────────────────────────────────────────────────
// T1 — sync a queue of 3 sales: 3 created, 0 replayed
// ─────────────────────────────────────────────────────────────────────────────

it('syncs_three_new_sales_with_no_replay', function (): void {
    $tenant = Tenant::create(['name' => 'Sync Corp A', 'status' => 'actif']);
    tenancy()->initialize($tenant);

    $session = makeSessionForSync();
    $product = makeServiceProduct('SYNC-P001');
    $device  = 'device-001';

    $requests = [
        buildRequest("{$device}:1", $session->id, $product->id),
        buildRequest("{$device}:2", $session->id, $product->id),
        buildRequest("{$device}:3", $session->id, $product->id),
    ];

    $result = (new OfflineSyncService())->sync($requests);

    expect($result->processed)->toBe(3);
    expect($result->replayed)->toBe(0);
    expect(Sale::count())->toBe(3);

    tenancy()->end();
});

// ─────────────────────────────────────────────────────────────────────────────
// T2 — replaying the same queue: 0 created, 3 replayed, identical state
// ─────────────────────────────────────────────────────────────────────────────

it('replaying_same_queue_is_idempotent', function (): void {
    $tenant = Tenant::create(['name' => 'Sync Corp B', 'status' => 'actif']);
    tenancy()->initialize($tenant);

    $session = makeSessionForSync();
    $product = makeServiceProduct('SYNC-P002');
    $device  = 'device-002';

    $requests = [
        buildRequest("{$device}:1", $session->id, $product->id),
        buildRequest("{$device}:2", $session->id, $product->id),
    ];

    $service = new OfflineSyncService();
    $first   = $service->sync($requests);
    $second  = $service->sync($requests);

    expect($first->processed)->toBe(2);
    expect($second->processed)->toBe(0);
    expect($second->replayed)->toBe(2);
    expect(Sale::count())->toBe(2);

    // Numbers assigned at first sync must not change on replay
    $numbers = Sale::orderBy('number')->pluck('number')->toArray();
    expect(count(array_unique($numbers)))->toBe(2);

    tenancy()->end();
});

// ─────────────────────────────────────────────────────────────────────────────
// T3 — mixed queue: 1 new + 1 already known
// ─────────────────────────────────────────────────────────────────────────────

it('mixed_queue_reports_correctly', function (): void {
    $tenant = Tenant::create(['name' => 'Sync Corp C', 'status' => 'actif']);
    tenancy()->initialize($tenant);

    $session = makeSessionForSync();
    $product = makeServiceProduct('SYNC-P003');
    $device  = 'device-003';

    $service = new OfflineSyncService();

    // First: send operation 1
    $service->sync([buildRequest("{$device}:1", $session->id, $product->id)]);

    // Then: send 1 (already known) + 2 (new)
    $result = $service->sync([
        buildRequest("{$device}:1", $session->id, $product->id),
        buildRequest("{$device}:2", $session->id, $product->id),
    ]);

    expect($result->processed)->toBe(1);
    expect($result->replayed)->toBe(1);
    expect(Sale::count())->toBe(2);

    tenancy()->end();
});

// ─────────────────────────────────────────────────────────────────────────────
// T4 — queue of 500 sales processes without error
// ─────────────────────────────────────────────────────────────────────────────

it('processes_500_offline_sales_without_error', function (): void {
    $tenant = Tenant::create(['name' => 'Sync Corp D', 'status' => 'actif']);
    tenancy()->initialize($tenant);

    $session  = makeSessionForSync();
    $product  = makeServiceProduct('SYNC-P004');
    $device   = 'device-004';
    $requests = [];

    for ($i = 1; $i <= 500; $i++) {
        $requests[] = buildRequest("{$device}:{$i}", $session->id, $product->id);
    }

    $result = (new OfflineSyncService())->sync($requests);

    expect($result->processed)->toBe(500);
    expect($result->replayed)->toBe(0);
    expect(Sale::count())->toBe(500);

    tenancy()->end();
});

// ─────────────────────────────────────────────────────────────────────────────
// T5 — interrupted sync resumes at first unconfirmed element
// ─────────────────────────────────────────────────────────────────────────────

it('interrupted_sync_resumes_without_duplication', function (): void {
    $tenant = Tenant::create(['name' => 'Sync Corp E', 'status' => 'actif']);
    tenancy()->initialize($tenant);

    $session = makeSessionForSync();
    $product = makeServiceProduct('SYNC-P005');
    $device  = 'device-005';

    $service = new OfflineSyncService();

    // First batch: 1 and 2 confirmed
    $service->sync([
        buildRequest("{$device}:1", $session->id, $product->id),
        buildRequest("{$device}:2", $session->id, $product->id),
    ]);

    // Second batch: start from 2 (overlap) + 3 (new)
    $result = $service->sync([
        buildRequest("{$device}:2", $session->id, $product->id),
        buildRequest("{$device}:3", $session->id, $product->id),
    ]);

    expect($result->processed)->toBe(1);
    expect($result->replayed)->toBe(1);
    expect(Sale::count())->toBe(3);

    tenancy()->end();
});
