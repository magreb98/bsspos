<?php

declare(strict_types=1);

/**
 * HTTP endpoint tests — Cash session & sale lifecycle — Lot 12
 *
 * Endpoints under test:
 *   POST   /commerce/cash-sessions                 → permission:cash-session.manage
 *   GET    /commerce/cash-sessions/active           → permission:cash-session.manage
 *   PATCH  /commerce/cash-sessions/{id}/close      → permission:cash-session.manage
 *   POST   /commerce/sales                          → permission:sale.write
 *   GET    /commerce/sales/{id}                     → permission:sale.write
 *   PATCH  /commerce/sales/{id}/confirm             → permission:sale.write
 *   POST   /commerce/sales/{id}/lines               → permission:sale.write
 *   DELETE /commerce/sales/{id}/lines/{line}        → permission:sale.write
 *   POST   /commerce/sales/{id}/payments            → permission:payment.collect
 *
 * Decisions under test:
 *   CS1  — open a cash session → 201
 *   CS2  — duplicate open session → 409 SESSION_ALREADY_OPEN
 *   CS3  — get active session → 200
 *   CS4  — close a session → 200
 *   CS5  — create a draft sale → 201
 *   CS6  — add a line to draft sale → 201, totals computed (integer, no float)
 *   CS7  — remove a line → 204
 *   CS8  — confirm a sale → 200, state = confirmed, number assigned
 *   CS9  — record cash payment → 201, status = confirmed
 *   CS10 — add line to confirmed sale → 409 SALE_NOT_DRAFT
 *
 * Phone range makeCsTenant : +237601020001 → +237601020020
 *
 * Note CS8/CS10: Sale is prepared via models to avoid PHP 8.4 SQLite
 * `PDO::exec('BEGIN DEFERRED TRANSACTION')` limitation in multi-request tests.
 * The single HTTP call to the confirm endpoint is the subject under test.
 */

use App\Control\Domaine;
use App\Control\Tenant;
use App\Platform\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Commerce\Internal\Enums\Granularity;
use Modules\Commerce\Internal\Enums\SaleState;
use Modules\Commerce\Internal\Enums\SessionState;
use Modules\Commerce\Internal\Models\CashRegister;
use Modules\Commerce\Internal\Models\CashSession;
use Modules\Commerce\Internal\Models\Family;
use Modules\Commerce\Internal\Models\OrganizationalUnit;
use Modules\Commerce\Internal\Models\PointOfSale;
use Modules\Commerce\Internal\Models\Product;
use Modules\Commerce\Internal\Models\Sale;
use Modules\Commerce\Internal\Models\SaleLine;
use Ramsey\Uuid\Uuid;

uses(RefreshDatabase::class);

// ─────────────────────────────────────────────────────────────────────────────
// Helpers
// ─────────────────────────────────────────────────────────────────────────────

/**
 * @return array{0: Tenant, 1: User, 2: string}
 */
function makeCsTenant(string $tag, string $phone): array
{
    $domain = "cs-{$tag}.bsspos.cm";

    $tenant = Tenant::create(['name' => "CS HTTP {$tag}", 'status' => 'actif']);
    Domaine::create(['tenant_id' => $tenant->id, 'domaine' => $domain]);

    tenancy()->initialize($tenant);

    seedTenantRolesAndPermissions();

    $user = User::create([
        'first_name' => 'CS',
        'last_name'  => 'Tester',
        'phone'      => $phone,
        'password'   => bcrypt('pass'),
        'active'     => true,
    ]);

    $user->assignRole('proprietaire');

    tenancy()->end();

    return [$tenant, $user, $domain];
}

function makeCsRegister(Tenant $tenant, string $suffix): CashRegister
{
    tenancy()->initialize($tenant);

    $unit     = OrganizationalUnit::create(['name' => "CS-Org-{$suffix}", 'active' => true]);
    $pos      = PointOfSale::create(['name' => "CS-POS-{$suffix}", 'organizational_unit_id' => $unit->id, 'active' => true]);
    $register = CashRegister::create(['name' => "CS-Reg-{$suffix}", 'point_of_sale_id' => $pos->id, 'active' => true]);

    tenancy()->end();

    return $register;
}

function makeCsProduct(Tenant $tenant, string $suffix, int $price = 50000): Product
{
    tenancy()->initialize($tenant);

    $family  = Family::firstOrCreate(['name' => "CS-Famille-{$suffix}"]);
    $product = Product::create([
        'reference'     => "CS-REF-{$suffix}",
        'label'         => "CS Produit {$suffix}",
        'family_id'     => $family->id,
        'selling_price' => $price,
        'vat_rate'      => '19.25',
        'granularity'   => Granularity::Quantity,
        'active'        => true,
    ]);

    tenancy()->end();

    return $product;
}

// ─────────────────────────────────────────────────────────────────────────────
// CS1 — open a cash session → 201
// ─────────────────────────────────────────────────────────────────────────────

it('cs1_open_cash_session_returns_201', function (): void {
    [$tenant, $user, $domain] = makeCsTenant('cs1', '+237601020001');
    $register = makeCsRegister($tenant, 'cs1');

    $this->actingAs($user)
        ->withHeaders(['Host' => $domain])
        ->postJson('/commerce/cash-sessions', [
            'cash_register_id' => $register->id,
            'opening_balance'  => 50000,
        ])
        ->assertStatus(201)
        ->assertJsonPath('data.cash_register_id', $register->id);
});

// ─────────────────────────────────────────────────────────────────────────────
// CS2 — duplicate open session → 409
// ─────────────────────────────────────────────────────────────────────────────

it('cs2_duplicate_session_returns_409', function (): void {
    [$tenant, $user, $domain] = makeCsTenant('cs2', '+237601020002');
    $register = makeCsRegister($tenant, 'cs2');

    tenancy()->initialize($tenant);
    CashSession::create([
        'cash_register_id' => $register->id,
        'state'            => SessionState::Open,
        'opened_at'        => now(),
        'opening_balance'  => 0,
        'opened_by'        => Uuid::uuid7()->toString(),
    ]);
    tenancy()->end();

    $this->actingAs($user)
        ->withHeaders(['Host' => $domain])
        ->postJson('/commerce/cash-sessions', [
            'cash_register_id' => $register->id,
            'opening_balance'  => 0,
        ])
        ->assertStatus(409)
        ->assertJsonPath('code', 'SESSION_ALREADY_OPEN');
});

// ─────────────────────────────────────────────────────────────────────────────
// CS3 — get active session → 200
// ─────────────────────────────────────────────────────────────────────────────

it('cs3_get_active_session_returns_200', function (): void {
    [$tenant, $user, $domain] = makeCsTenant('cs3', '+237601020003');
    $register = makeCsRegister($tenant, 'cs3');

    tenancy()->initialize($tenant);
    $session = CashSession::create([
        'cash_register_id' => $register->id,
        'state'            => SessionState::Open,
        'opened_at'        => now(),
        'opening_balance'  => 10000,
        'opened_by'        => Uuid::uuid7()->toString(),
    ]);
    tenancy()->end();

    $this->actingAs($user)
        ->withHeaders(['Host' => $domain])
        ->getJson('/commerce/cash-sessions/active?cash_register_id=' . $register->id)
        ->assertStatus(200)
        ->assertJsonPath('data.id', $session->id);
});

// ─────────────────────────────────────────────────────────────────────────────
// CS4 — close session → 200, state closed
// ─────────────────────────────────────────────────────────────────────────────

it('cs4_close_session_returns_200', function (): void {
    [$tenant, $user, $domain] = makeCsTenant('cs4', '+237601020004');
    $register = makeCsRegister($tenant, 'cs4');

    tenancy()->initialize($tenant);
    $session = CashSession::create([
        'cash_register_id' => $register->id,
        'state'            => SessionState::Open,
        'opened_at'        => now(),
        'opening_balance'  => 0,
        'opened_by'        => Uuid::uuid7()->toString(),
    ]);
    tenancy()->end();

    $this->actingAs($user)
        ->withHeaders(['Host' => $domain])
        ->patchJson("/commerce/cash-sessions/{$session->id}/close", ['closing_balance' => 75000])
        ->assertStatus(200)
        ->assertJsonPath('data.state', 'closed');
});

// ─────────────────────────────────────────────────────────────────────────────
// CS5 — create a draft sale → 201
// ─────────────────────────────────────────────────────────────────────────────

it('cs5_create_draft_sale_returns_201', function (): void {
    [$tenant, $user, $domain] = makeCsTenant('cs5', '+237601020005');
    $register = makeCsRegister($tenant, 'cs5');

    tenancy()->initialize($tenant);
    $session = CashSession::create([
        'cash_register_id' => $register->id,
        'state'            => SessionState::Open,
        'opened_at'        => now(),
        'opening_balance'  => 0,
        'opened_by'        => Uuid::uuid7()->toString(),
    ]);
    tenancy()->end();

    $this->actingAs($user)
        ->withHeaders(['Host' => $domain])
        ->postJson('/commerce/sales', ['cash_session_id' => $session->id])
        ->assertStatus(201)
        ->assertJsonPath('data.state', 'draft');
});

// ─────────────────────────────────────────────────────────────────────────────
// CS6 — add a line → 201, totals are integers (no float)
// ─────────────────────────────────────────────────────────────────────────────

it('cs6_add_line_computes_integer_totals', function (): void {
    [$tenant, $user, $domain] = makeCsTenant('cs6', '+237601020006');
    $register = makeCsRegister($tenant, 'cs6');
    $product  = makeCsProduct($tenant, 'cs6', 100000);

    tenancy()->initialize($tenant);
    $session = CashSession::create([
        'cash_register_id' => $register->id,
        'state'            => SessionState::Open,
        'opened_at'        => now(),
        'opening_balance'  => 0,
        'opened_by'        => Uuid::uuid7()->toString(),
    ]);
    $sale = Sale::create([
        'cash_session_id' => $session->id,
        'state'           => SaleState::Draft,
        'idempotency_key' => Uuid::uuid7()->toString(),
    ]);
    tenancy()->end();

    $response = $this->actingAs($user)
        ->withHeaders(['Host' => $domain])
        ->postJson("/commerce/sales/{$sale->id}/lines", [
            'product_id' => $product->id,
            'quantity'   => 2,
        ])
        ->assertStatus(201);

    // 100000 * 2 = 200000 HT ; VAT 19.25% = 38500 ; TTC = 238500
    expect($response->json('data.line_total_excluding_tax'))->toBe(200000)
        ->and($response->json('data.line_total_tax'))->toBe(38500)
        ->and($response->json('data.line_total_including_tax'))->toBe(238500);
});

// ─────────────────────────────────────────────────────────────────────────────
// CS7 — remove a line → 204
// ─────────────────────────────────────────────────────────────────────────────

it('cs7_remove_line_returns_204', function (): void {
    [$tenant, $user, $domain] = makeCsTenant('cs7', '+237601020007');
    $register = makeCsRegister($tenant, 'cs7');
    $product  = makeCsProduct($tenant, 'cs7');

    tenancy()->initialize($tenant);
    $session = CashSession::create([
        'cash_register_id' => $register->id,
        'state'            => SessionState::Open,
        'opened_at'        => now(),
        'opening_balance'  => 0,
        'opened_by'        => Uuid::uuid7()->toString(),
    ]);
    $sale = Sale::create([
        'cash_session_id' => $session->id,
        'state'           => SaleState::Draft,
        'idempotency_key' => Uuid::uuid7()->toString(),
    ]);
    $line = SaleLine::create([
        'sale_id'                  => $sale->id,
        'product_id'               => $product->id,
        'designation'              => 'CS7 Line',
        'unit_price'               => 50000,
        'vat_rate'                 => '19.25',
        'quantity'                 => 1,
        'line_total_excluding_tax' => 50000,
        'line_total_tax'           => 9625,
        'line_total_including_tax' => 59625,
        'allocations'              => [],
    ]);
    tenancy()->end();

    $this->actingAs($user)
        ->withHeaders(['Host' => $domain])
        ->deleteJson("/commerce/sales/{$sale->id}/lines/{$line->id}")
        ->assertStatus(204);
});

// ─────────────────────────────────────────────────────────────────────────────
// CS8 — confirm a sale → 200, state = confirmed, number assigned
// Sale and line prepared via models; single HTTP request tests the endpoint.
// ─────────────────────────────────────────────────────────────────────────────

it('cs8_confirm_sale_returns_200_with_number', function (): void {
    [$tenant, $user, $domain] = makeCsTenant('cs8', '+237601020008');
    $register = makeCsRegister($tenant, 'cs8');
    $product  = makeCsProduct($tenant, 'cs8');

    tenancy()->initialize($tenant);

    $session = CashSession::create([
        'cash_register_id' => $register->id,
        'state'            => SessionState::Open,
        'opened_at'        => now(),
        'opening_balance'  => 0,
        'opened_by'        => Uuid::uuid7()->toString(),
    ]);

    $sale = Sale::create([
        'cash_session_id' => $session->id,
        'state'           => SaleState::Draft,
        'idempotency_key' => Uuid::uuid7()->toString(),
    ]);

    SaleLine::create([
        'sale_id'                  => $sale->id,
        'product_id'               => $product->id,
        'designation'              => 'CS8 Produit',
        'unit_price'               => 50000,
        'vat_rate'                 => '19.25',
        'quantity'                 => 1,
        'line_total_excluding_tax' => 50000,
        'line_total_tax'           => 9625,
        'line_total_including_tax' => 59625,
        'allocations'              => [],
    ]);

    tenancy()->end();

    $response = $this->actingAs($user)
        ->withHeaders(['Host' => $domain])
        ->patchJson("/commerce/sales/{$sale->id}/confirm")
        ->assertStatus(200)
        ->assertJsonPath('data.state', 'confirmed');

    expect($response->json('data.number'))->toStartWith('V-');
});

// ─────────────────────────────────────────────────────────────────────────────
// CS9 — cash payment → 201, status confirmed
// ─────────────────────────────────────────────────────────────────────────────

it('cs9_cash_payment_returns_201_status_confirmed', function (): void {
    [$tenant, $user, $domain] = makeCsTenant('cs9', '+237601020009');
    $register = makeCsRegister($tenant, 'cs9');

    tenancy()->initialize($tenant);
    $session = CashSession::create([
        'cash_register_id' => $register->id,
        'state'            => SessionState::Open,
        'opened_at'        => now(),
        'opening_balance'  => 0,
        'opened_by'        => Uuid::uuid7()->toString(),
    ]);
    $sale = Sale::create([
        'cash_session_id' => $session->id,
        'state'           => SaleState::Draft,
        'idempotency_key' => Uuid::uuid7()->toString(),
    ]);
    tenancy()->end();

    $this->actingAs($user)
        ->withHeaders(['Host' => $domain])
        ->postJson("/commerce/sales/{$sale->id}/payments", [
            'method' => 'cash',
            'amount' => 50000,
        ])
        ->assertStatus(201)
        ->assertJsonPath('data.status', 'confirmed')
        ->assertJsonPath('data.method', 'cash');
});

// ─────────────────────────────────────────────────────────────────────────────
// CS10 — add line to confirmed sale → 409 SALE_NOT_DRAFT
// Confirmed sale prepared via models; single HTTP request tests the guard.
// ─────────────────────────────────────────────────────────────────────────────

it('cs10_add_line_to_confirmed_sale_returns_409', function (): void {
    [$tenant, $user, $domain] = makeCsTenant('cs10', '+237601020010');
    $product = makeCsProduct($tenant, 'cs10');
    $register = makeCsRegister($tenant, 'cs10');

    tenancy()->initialize($tenant);
    $session = CashSession::create([
        'cash_register_id' => $register->id,
        'state'            => SessionState::Open,
        'opened_at'        => now(),
        'opening_balance'  => 0,
        'opened_by'        => Uuid::uuid7()->toString(),
    ]);
    $sale = Sale::create([
        'cash_session_id'     => $session->id,
        'state'               => SaleState::Confirmed,
        'number'              => 'V-2026-0001',
        'idempotency_key'     => Uuid::uuid7()->toString(),
        'total_excluding_tax' => 50000,
        'total_tax'           => 9625,
        'total_including_tax' => 59625,
        'confirmed_at'        => now(),
    ]);
    tenancy()->end();

    $this->actingAs($user)
        ->withHeaders(['Host' => $domain])
        ->postJson("/commerce/sales/{$sale->id}/lines", [
            'product_id' => $product->id,
            'quantity'   => 1,
        ])
        ->assertStatus(409)
        ->assertJsonPath('code', 'SALE_NOT_DRAFT');
});
