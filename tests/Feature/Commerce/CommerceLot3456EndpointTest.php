<?php

declare(strict_types=1);

/**
 * HTTP endpoint tests — Lots 3 / 4 / 5 / 6
 *
 * Endpoints under test:
 *   POST   /commerce/suppliers                           → supplier.write (gérant)
 *   POST   /commerce/supplier-orders                     → supplier-order.write (gérant)
 *   POST   /commerce/supplier-orders/{id}/receptions     → supplier-order.write (gérant)
 *   POST   /commerce/inventory-counts                    → inventory.write (gérant)
 *   POST   /commerce/expenses                            → expense.write (vendeur)
 *   POST   /commerce/cash-sessions/{id}/closing          → cash-session.manage (vendeur)
 *   POST   /commerce/transfers                           → transfer.write (gérant)
 *   PATCH  /commerce/transfers/{id}/dispatch             → transfer.write (gérant)
 *   PATCH  /commerce/transfers/{id}/receive              → transfer.write (gérant)
 *   POST   /commerce/sync                               → sync.push (vendeur)
 *   GET    /commerce/dashboard                          → dashboard.view (vendeur)
 *   GET    /commerce/points-of-sale                     → pos.write (proprietaire)
 *
 * Decisions:
 *   L4_1  — POST supplier → 201 with name
 *   L4_2  — POST supplier-order with lines → 201
 *   L4_3  — POST reception → 201, stock incremented
 *   L4_4  — POST inventory-count → 201 with adjustment lines
 *   L4_5  — POST expense → 201
 *   L4_6  — POST cash-closing → 201 with calculated amounts
 *   L5_1  — POST transfer → 201 (pending)
 *   L5_2  — PATCH dispatch → 200, source stock decremented
 *   L5_3  — PATCH receive → 200, destination stock incremented
 *   L3_1  — POST sync with 2 sales (1 new, 1 replay) → processed=1 replayed=1
 *   L6_1  — GET dashboard → 200 with summary shape
 *   L5_4  — GET points-of-sale → 200
 *
 * Phone range : +237601050001 → +237601050020
 */

use App\Control\Domaine;
use App\Control\Tenant;
use App\Platform\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Commerce\Internal\Enums\Granularity;
use Modules\Commerce\Internal\Enums\SessionState;
use Modules\Commerce\Internal\Enums\SupplierOrderStatus;
use Modules\Commerce\Internal\Enums\TransferStatus;
use Modules\Commerce\Internal\Models\CashRegister;
use Modules\Commerce\Internal\Models\CashSession;
use Modules\Commerce\Internal\Models\Family;
use Modules\Commerce\Internal\Models\OrganizationalUnit;
use Modules\Commerce\Internal\Models\PointOfSale;
use Modules\Commerce\Internal\Models\Product;
use Modules\Commerce\Internal\Models\StockMovement;
use Modules\Commerce\Internal\Models\Supplier;
use Modules\Commerce\Internal\Models\SupplierOrder;
use Modules\Commerce\Internal\Models\Transfer;
use Ramsey\Uuid\Uuid;

uses(RefreshDatabase::class);

// ─────────────────────────────────────────────────────────────────────────────
// Helpers
// ─────────────────────────────────────────────────────────────────────────────

/**
 * @return array{0: Tenant, 1: User, 2: string}
 */
function makeLotTenant(string $tag, string $phone, string $role = 'gérant'): array
{
    $domain = "lot-{$tag}.bsspos.cm";
    $tenant = Tenant::create(['name' => "LOT {$tag}", 'status' => 'actif']);
    Domaine::create(['tenant_id' => $tenant->id, 'domaine' => $domain]);

    tenancy()->initialize($tenant);
    seedTenantRolesAndPermissions();

    $user = User::create([
        'first_name' => 'LOT',
        'last_name'  => 'Tester',
        'phone'      => $phone,
        'password'   => bcrypt('pass'),
        'active'     => true,
    ]);
    $user->assignRole($role);

    tenancy()->end();

    return [$tenant, $user, $domain];
}

function makeLotProduct(Tenant $tenant, string $ref, Granularity $granularity = Granularity::Quantity): Product
{
    tenancy()->initialize($tenant);
    $family  = Family::firstOrCreate(['name' => 'LOT Family', 'active' => true]);
    $product = Product::create([
        'reference'     => $ref,
        'label'         => "LOT Prod {$ref}",
        'family_id'     => $family->id,
        'selling_price' => 50000,
        'vat_rate'      => '0',
        'granularity'   => $granularity,
        'active'        => true,
    ]);
    tenancy()->end();

    return $product;
}

function makeLotPos(Tenant $tenant, string $name): PointOfSale
{
    tenancy()->initialize($tenant);
    $unit = OrganizationalUnit::create(['name' => "{$name} HQ", 'active' => true]);
    $pos  = PointOfSale::create(['name' => $name, 'organizational_unit_id' => $unit->id, 'active' => true]);
    tenancy()->end();

    return $pos;
}

function makeLotSession(Tenant $tenant, PointOfSale $pos, string $userId): CashSession
{
    tenancy()->initialize($tenant);
    $register = CashRegister::create(['name' => 'LOT Caisse', 'point_of_sale_id' => $pos->id, 'active' => true]);
    $session  = CashSession::create([
        'cash_register_id' => $register->id,
        'state'            => SessionState::Open,
        'opened_at'        => now(),
        'opening_balance'  => 10000,
        'opened_by'        => $userId,
    ]);
    tenancy()->end();

    return $session;
}

// ─────────────────────────────────────────────────────────────────────────────
// L4_1 — POST supplier → 201
// ─────────────────────────────────────────────────────────────────────────────

it('l4_1_post_supplier_returns_201', function (): void {
    [, $user, $domain] = makeLotTenant('l41', '+237601050001');

    $this->actingAs($user)
        ->withHeaders(['Host' => $domain])
        ->postJson('/commerce/suppliers', ['name' => 'Fournisseur XYZ'])
        ->assertStatus(201)
        ->assertJsonPath('data.name', 'Fournisseur XYZ');
});

// ─────────────────────────────────────────────────────────────────────────────
// L4_2 — POST supplier-order with lines → 201
// ─────────────────────────────────────────────────────────────────────────────

it('l4_2_post_supplier_order_returns_201', function (): void {
    [$tenant, $user, $domain] = makeLotTenant('l42', '+237601050002');
    $product = makeLotProduct($tenant, 'L42-P1');

    tenancy()->initialize($tenant);
    $supplier = Supplier::create(['name' => 'Fournisseur L42', 'active' => true]);
    tenancy()->end();

    $this->actingAs($user)
        ->withHeaders(['Host' => $domain])
        ->postJson('/commerce/supplier-orders', [
            'supplier_id' => $supplier->id,
            'lines'       => [
                ['product_id' => $product->id, 'quantity' => 10, 'unit_cost' => 20000],
            ],
        ])
        ->assertStatus(201)
        ->assertJsonPath('data.supplier.id', $supplier->id)
        ->assertJsonCount(1, 'data.lines');
});

// ─────────────────────────────────────────────────────────────────────────────
// L4_3 — POST reception → 201, stock incremented
// ─────────────────────────────────────────────────────────────────────────────

it('l4_3_post_reception_increments_stock', function (): void {
    [$tenant, $user, $domain] = makeLotTenant('l43', '+237601050003');
    $product = makeLotProduct($tenant, 'L43-P1');
    $pos     = makeLotPos($tenant, 'LOT43 POS');

    tenancy()->initialize($tenant);
    $supplier = Supplier::create(['name' => 'Fournisseur L43', 'active' => true]);
    $order    = SupplierOrder::create([
        'supplier_id' => $supplier->id,
        'status'      => SupplierOrderStatus::Sent,
        'ordered_at'  => now(),
    ]);
    tenancy()->end();

    $this->actingAs($user)
        ->withHeaders(['Host' => $domain])
        ->postJson("/commerce/supplier-orders/{$order->id}/receptions", [
            'point_of_sale_id' => $pos->id,
            'lines'            => [
                [
                    'product_id'        => $product->id,
                    'quantity_expected' => 5,
                    'quantity_received' => 5,
                    'unit_cost'         => 20000,
                ],
            ],
        ])
        ->assertStatus(201);

    tenancy()->initialize($tenant);
    expect(StockMovement::where('product_id', $product->id)->sum('quantity'))->toBe(5);
    tenancy()->end();
});

// ─────────────────────────────────────────────────────────────────────────────
// L4_4 — POST inventory-count → 201 with adjustment
// ─────────────────────────────────────────────────────────────────────────────

it('l4_4_post_inventory_count_creates_adjustment', function (): void {
    [$tenant, $user, $domain] = makeLotTenant('l44', '+237601050004');
    $product = makeLotProduct($tenant, 'L44-P1');
    $pos     = makeLotPos($tenant, 'LOT44 POS');

    tenancy()->initialize($tenant);
    StockMovement::create([
        'point_of_sale_id' => $pos->id,
        'product_id'       => $product->id,
        'sale_line_id'     => null,
        'quantity'         => 10,
        'occurred_at'      => now(),
    ]);
    tenancy()->end();

    $this->actingAs($user)
        ->withHeaders(['Host' => $domain])
        ->postJson('/commerce/inventory-counts', [
            'point_of_sale_id' => $pos->id,
            'lines'            => [
                ['product_id' => $product->id, 'counted_quantity' => 8],
            ],
            'notes' => 'Inventaire mensuel',
        ])
        ->assertStatus(201)
        ->assertJsonPath('data.lines.0.adjustment', -2);
});

// ─────────────────────────────────────────────────────────────────────────────
// L4_5 — POST expense → 201
// ─────────────────────────────────────────────────────────────────────────────

it('l4_5_post_expense_returns_201', function (): void {
    [$tenant, $user, $domain] = makeLotTenant('l45', '+237601050005', 'vendeur');
    $pos     = makeLotPos($tenant, 'LOT45 POS');

    tenancy()->initialize($tenant);
    $userId = $user->id;
    tenancy()->end();

    $session = makeLotSession($tenant, $pos, $userId);

    $this->actingAs($user)
        ->withHeaders(['Host' => $domain])
        ->postJson('/commerce/expenses', [
            'cash_session_id' => $session->id,
            'amount'          => 5000,
            'label'           => 'Eau minérale',
        ])
        ->assertStatus(201)
        ->assertJsonPath('data.amount', 5000);
});

// ─────────────────────────────────────────────────────────────────────────────
// L4_6 — POST cash-closing → 201 with calculated amounts
// ─────────────────────────────────────────────────────────────────────────────

it('l4_6_post_cash_closing_calculates_amounts', function (): void {
    [$tenant, $user, $domain] = makeLotTenant('l46', '+237601050006');
    $pos     = makeLotPos($tenant, 'LOT46 POS');

    tenancy()->initialize($tenant);
    $userId = $user->id;
    tenancy()->end();

    $session = makeLotSession($tenant, $pos, $userId);

    $this->actingAs($user)
        ->withHeaders(['Host' => $domain])
        ->postJson("/commerce/cash-sessions/{$session->id}/closing", [
            'declared_cash' => 10000,
        ])
        ->assertStatus(201)
        ->assertJsonPath('data.opening_balance', 10000)
        ->assertJsonPath('data.declared_cash', 10000)
        ->assertJsonPath('data.expected_cash', 10000)
        ->assertJsonPath('data.discrepancy', 0);
});

// ─────────────────────────────────────────────────────────────────────────────
// L5_1 — POST transfer → 201 (pending)
// ─────────────────────────────────────────────────────────────────────────────

it('l5_1_post_transfer_returns_201_pending', function (): void {
    [$tenant, $user, $domain] = makeLotTenant('l51', '+237601050007');
    $product = makeLotProduct($tenant, 'L51-P1');
    $source  = makeLotPos($tenant, 'LOT51 Source');
    $dest    = makeLotPos($tenant, 'LOT51 Dest');

    $this->actingAs($user)
        ->withHeaders(['Host' => $domain])
        ->postJson('/commerce/transfers', [
            'source_pos_id'      => $source->id,
            'destination_pos_id' => $dest->id,
            'lines'              => [
                ['product_id' => $product->id, 'quantity' => 3],
            ],
        ])
        ->assertStatus(201)
        ->assertJsonPath('data.status', TransferStatus::Pending->value);
});

// ─────────────────────────────────────────────────────────────────────────────
// L5_2 — PATCH dispatch → 200, source stock decremented
// ─────────────────────────────────────────────────────────────────────────────

it('l5_2_dispatch_decrements_source_stock', function (): void {
    [$tenant, $user, $domain] = makeLotTenant('l52', '+237601050008');
    $product = makeLotProduct($tenant, 'L52-P1');
    $source  = makeLotPos($tenant, 'LOT52 Source');
    $dest    = makeLotPos($tenant, 'LOT52 Dest');

    tenancy()->initialize($tenant);
    StockMovement::create(['point_of_sale_id' => $source->id, 'product_id' => $product->id, 'sale_line_id' => null, 'quantity' => 10, 'occurred_at' => now()]);
    $transfer = Transfer::create(['source_pos_id' => $source->id, 'destination_pos_id' => $dest->id, 'status' => TransferStatus::Pending]);
    $transfer->lines()->create(['product_id' => $product->id, 'quantity' => 3]);
    tenancy()->end();

    $this->actingAs($user)
        ->withHeaders(['Host' => $domain])
        ->patchJson("/commerce/transfers/{$transfer->id}/dispatch")
        ->assertStatus(200)
        ->assertJsonPath('data.status', TransferStatus::InTransit->value);

    tenancy()->initialize($tenant);
    expect(StockMovement::where('product_id', $product->id)->where('point_of_sale_id', $source->id)->sum('quantity'))->toBe(7);
    tenancy()->end();
});

// ─────────────────────────────────────────────────────────────────────────────
// L5_3 — PATCH receive → 200, destination stock incremented
// ─────────────────────────────────────────────────────────────────────────────

it('l5_3_receive_increments_destination_stock', function (): void {
    [$tenant, $user, $domain] = makeLotTenant('l53', '+237601050009');
    $product = makeLotProduct($tenant, 'L53-P1');
    $source  = makeLotPos($tenant, 'LOT53 Source');
    $dest    = makeLotPos($tenant, 'LOT53 Dest');

    tenancy()->initialize($tenant);
    $transfer = Transfer::create(['source_pos_id' => $source->id, 'destination_pos_id' => $dest->id, 'status' => TransferStatus::InTransit, 'dispatched_at' => now()]);
    $transfer->lines()->create(['product_id' => $product->id, 'quantity' => 4]);
    tenancy()->end();

    $this->actingAs($user)
        ->withHeaders(['Host' => $domain])
        ->patchJson("/commerce/transfers/{$transfer->id}/receive")
        ->assertStatus(200)
        ->assertJsonPath('data.status', TransferStatus::Received->value);

    tenancy()->initialize($tenant);
    expect(StockMovement::where('product_id', $product->id)->where('point_of_sale_id', $dest->id)->sum('quantity'))->toBe(4);
    tenancy()->end();
});

// ─────────────────────────────────────────────────────────────────────────────
// L3_1 — POST sync with 1 new + 1 replay → processed=1, replayed=1
// ─────────────────────────────────────────────────────────────────────────────

it('l3_1_sync_processes_new_and_replays_duplicate', function (): void {
    [$tenant, $user, $domain] = makeLotTenant('l31', '+237601050010', 'vendeur');
    $product = makeLotProduct($tenant, 'L31-P1');
    $pos     = makeLotPos($tenant, 'LOT31 POS');

    tenancy()->initialize($tenant);
    $userId = $user->id;
    tenancy()->end();

    $session = makeLotSession($tenant, $pos, $userId);

    $key  = Uuid::uuid7()->toString();
    $line = [
        'product_id'               => $product->id,
        'quantity'                 => 1,
        'designation'              => 'LOT31 Prod',
        'unit_price'               => 50000,
        'vat_rate'                 => '0',
        'line_total_excluding_tax' => 50000,
        'line_total_tax'           => 0,
        'line_total_including_tax' => 50000,
    ];

    $payload = [
        'sales' => [
            ['idempotency_key' => $key, 'cash_session_id' => $session->id, 'lines' => [$line]],
            ['idempotency_key' => $key, 'cash_session_id' => $session->id, 'lines' => [$line]],
        ],
    ];

    $this->actingAs($user)
        ->withHeaders(['Host' => $domain])
        ->postJson('/commerce/sync', $payload)
        ->assertStatus(200)
        ->assertJsonPath('data.processed', 1)
        ->assertJsonPath('data.replayed', 1);
});

// ─────────────────────────────────────────────────────────────────────────────
// L6_1 — GET dashboard → 200 with expected shape
// ─────────────────────────────────────────────────────────────────────────────

it('l6_1_get_dashboard_returns_200_with_shape', function (): void {
    [, $user, $domain] = makeLotTenant('l61', '+237601050011', 'vendeur');

    $this->actingAs($user)
        ->withHeaders(['Host' => $domain])
        ->getJson('/commerce/dashboard?from=2026-01-01&to=2026-08-28')
        ->assertStatus(200)
        ->assertJsonStructure(['data' => ['total_sale_count', 'total_including_tax', 'is_provisional', 'by_pos']]);
});

// ─────────────────────────────────────────────────────────────────────────────
// L5_4 — GET points-of-sale → 200 (proprietaire)
// ─────────────────────────────────────────────────────────────────────────────

it('l5_4_get_points_of_sale_returns_200', function (): void {
    [, $user, $domain] = makeLotTenant('l54', '+237601050012', 'proprietaire');

    $this->actingAs($user)
        ->withHeaders(['Host' => $domain])
        ->getJson('/commerce/points-of-sale')
        ->assertStatus(200)
        ->assertJsonStructure(['data']);
});
