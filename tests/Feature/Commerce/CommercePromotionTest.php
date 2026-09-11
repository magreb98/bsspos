<?php

declare(strict_types=1);

/**
 * HTTP endpoint tests — Promotions & Loyalty — Lot 13
 *
 * Endpoints under test:
 *   PATCH  /commerce/sales/{id}/confirm   (coupon_code body param)
 *
 * Decisions under test:
 *   PM1 — 10% family promotion applies automatically on confirm
 *   PM2 — single-use coupon is consumed; second use returns 422 COUPON_ERROR
 *   PM3 — offline replay idempotent: confirmed sale cannot re-trigger coupon
 *   PM4 — expired promotion is ignored on new sale; past discounts intact
 *   PM5 — non-cumulative promotions: best-discount wins
 *   PM6 — Σ(line TTC) == sale.total_including_tax after discount
 *   PM7 — SYSCOHADA export emits account 709 debit for discount amount
 *
 * Phone range: +237601030001 → +237601030020
 */

use App\Control\Domaine;
use App\Control\Tenant;
use App\Platform\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Commerce\Internal\Enums\Granularity;
use Modules\Commerce\Internal\Enums\PromotionScope;
use Modules\Commerce\Internal\Enums\PromotionType;
use Modules\Commerce\Internal\Enums\SaleState;
use Modules\Commerce\Internal\Enums\SessionState;
use Modules\Commerce\Internal\Models\CashRegister;
use Modules\Commerce\Internal\Models\CashSession;
use Modules\Commerce\Internal\Models\Coupon;
use Modules\Commerce\Internal\Models\Family;
use Modules\Commerce\Internal\Models\OrganizationalUnit;
use Modules\Commerce\Internal\Models\PointOfSale;
use Modules\Commerce\Internal\Models\Product;
use Modules\Commerce\Internal\Models\Promotion;
use Modules\Commerce\Internal\Models\Sale;
use Modules\Commerce\Internal\Models\SaleLine;
use Modules\Commerce\Internal\Services\SyscohadaExportService;
use Ramsey\Uuid\Uuid;

uses(RefreshDatabase::class);

// ─────────────────────────────────────────────────────────────────────────────
// Helpers
// ─────────────────────────────────────────────────────────────────────────────

/**
 * @return array{0: Tenant, 1: User, 2: string}
 */
function makePmTenant(string $tag, string $phone): array
{
    $domain = "pm-{$tag}.bsspos.cm";

    $tenant = Tenant::create(['name' => "PM HTTP {$tag}", 'status' => 'actif']);
    Domaine::create(['tenant_id' => $tenant->id, 'domaine' => $domain]);

    tenancy()->initialize($tenant);

    seedTenantRolesAndPermissions();

    $user = User::create([
        'first_name' => 'PM',
        'last_name'  => 'Tester',
        'phone'      => $phone,
        'password'   => bcrypt('pass'),
        'active'     => true,
    ]);

    $user->assignRole('proprietaire');

    tenancy()->end();

    return [$tenant, $user, $domain];
}

function makePmRegister(Tenant $tenant, string $suffix): CashRegister
{
    tenancy()->initialize($tenant);

    $unit     = OrganizationalUnit::create(['name' => "PM-Org-{$suffix}", 'active' => true]);
    $pos      = PointOfSale::create(['name' => "PM-POS-{$suffix}", 'organizational_unit_id' => $unit->id, 'active' => true]);
    $register = CashRegister::create(['name' => "PM-Reg-{$suffix}", 'point_of_sale_id' => $pos->id, 'active' => true]);

    tenancy()->end();

    return $register;
}

function makePmProduct(Tenant $tenant, string $suffix, ?string $familyId = null): Product
{
    tenancy()->initialize($tenant);

    $familyId = $familyId ?? Family::create(['name' => "PM-Family-{$suffix}"])->id;
    $product  = Product::create([
        'reference'     => "PM-REF-{$suffix}",
        'label'         => "PM Produit {$suffix}",
        'family_id'     => $familyId,
        'selling_price' => 100000,
        'vat_rate'      => '19.25',
        'granularity'   => Granularity::Quantity,
        'active'        => true,
    ]);

    tenancy()->end();

    return $product;
}

// ─────────────────────────────────────────────────────────────────────────────
// PM1 — 10% family promotion applies automatically on confirm
// ─────────────────────────────────────────────────────────────────────────────

it('pm1_family_percent_promotion_applies_on_confirm', function (): void {
    [$tenant, $user, $domain] = makePmTenant('pm1', '+237601030001');
    $register = makePmRegister($tenant, 'pm1');

    tenancy()->initialize($tenant);

    $family  = Family::create(['name' => 'PM1-Famille']);
    $product = Product::create([
        'reference'     => 'PM1-REF',
        'label'         => 'PM1 Produit',
        'family_id'     => $family->id,
        'selling_price' => 100000,
        'vat_rate'      => '0.00',
        'granularity'   => Granularity::Quantity,
        'active'        => true,
    ]);

    Promotion::create([
        'name'       => '10% famille',
        'type'       => PromotionType::Percent,
        'value'      => 1000,          // 1000 bp = 10%
        'scope'      => PromotionScope::Family,
        'scope_id'   => $family->id,
        'cumulative' => true,
        'active'     => true,
    ]);

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
        'designation'              => 'PM1 Produit',
        'unit_price'               => 100000,
        'vat_rate'                 => '0.00',
        'quantity'                 => 1,
        'line_total_excluding_tax' => 100000,
        'line_total_tax'           => 0,
        'line_total_including_tax' => 100000,
        'allocations'              => [],
        'discount_amount'          => 0,
    ]);

    tenancy()->end();

    $response = $this->actingAs($user)
        ->withHeaders(['Host' => $domain])
        ->patchJson("/commerce/sales/{$sale->id}/confirm")
        ->assertStatus(200)
        ->assertJsonPath('data.state', 'confirmed');

    // 10% of 100000 HT = 10000 discount, net HT = 90000, TTC = 90000 (0% VAT)
    expect($response->json('data.total_excluding_tax'))->toBe(90000);
    expect($response->json('data.total_including_tax'))->toBe(90000);

    $lines = $response->json('data.lines');
    expect($lines[0]['discount_amount'])->toBe(10000);
});

// ─────────────────────────────────────────────────────────────────────────────
// PM2 — single-use coupon consumed; second use → 422 COUPON_ERROR
// ─────────────────────────────────────────────────────────────────────────────

it('pm2_single_use_coupon_exhausted_on_second_sale', function (): void {
    [$tenant, $user, $domain] = makePmTenant('pm2', '+237601030002');
    $register = makePmRegister($tenant, 'pm2');

    tenancy()->initialize($tenant);

    $family  = Family::create(['name' => 'PM2-Famille']);
    $product = Product::create([
        'reference'     => 'PM2-REF',
        'label'         => 'PM2 Produit',
        'family_id'     => $family->id,
        'selling_price' => 50000,
        'vat_rate'      => '0.00',
        'granularity'   => Granularity::Quantity,
        'active'        => true,
    ]);

    $promotion = Promotion::create([
        'name'       => '5% coupon',
        'type'       => PromotionType::Percent,
        'value'      => 500,
        'scope'      => PromotionScope::Family,
        'scope_id'   => $family->id,
        'cumulative' => true,
        'active'     => true,
    ]);

    $coupon = Coupon::create([
        'code'         => 'PROMO5-PM2',
        'promotion_id' => $promotion->id,
        'max_uses'     => 1,
        'times_used'   => 0,
    ]);

    $session = CashSession::create([
        'cash_register_id' => $register->id,
        'state'            => SessionState::Open,
        'opened_at'        => now(),
        'opening_balance'  => 0,
        'opened_by'        => Uuid::uuid7()->toString(),
    ]);

    // Sale 1
    $sale1 = Sale::create([
        'cash_session_id' => $session->id,
        'state'           => SaleState::Draft,
        'idempotency_key' => Uuid::uuid7()->toString(),
    ]);
    SaleLine::create([
        'sale_id'                  => $sale1->id,
        'product_id'               => $product->id,
        'designation'              => 'PM2 Produit',
        'unit_price'               => 50000,
        'vat_rate'                 => '0.00',
        'quantity'                 => 1,
        'line_total_excluding_tax' => 50000,
        'line_total_tax'           => 0,
        'line_total_including_tax' => 50000,
        'allocations'              => [],
        'discount_amount'          => 0,
    ]);

    // Sale 2
    $sale2 = Sale::create([
        'cash_session_id' => $session->id,
        'state'           => SaleState::Draft,
        'idempotency_key' => Uuid::uuid7()->toString(),
    ]);
    SaleLine::create([
        'sale_id'                  => $sale2->id,
        'product_id'               => $product->id,
        'designation'              => 'PM2 Produit',
        'unit_price'               => 50000,
        'vat_rate'                 => '0.00',
        'quantity'                 => 1,
        'line_total_excluding_tax' => 50000,
        'line_total_tax'           => 0,
        'line_total_including_tax' => 50000,
        'allocations'              => [],
        'discount_amount'          => 0,
    ]);

    tenancy()->end();

    // First confirm — should succeed, coupon consumed
    $this->actingAs($user)
        ->withHeaders(['Host' => $domain])
        ->patchJson("/commerce/sales/{$sale1->id}/confirm", ['coupon_code' => 'PROMO5-PM2'])
        ->assertStatus(200);

    // Second confirm with same coupon — should fail
    $this->actingAs($user)
        ->withHeaders(['Host' => $domain])
        ->patchJson("/commerce/sales/{$sale2->id}/confirm", ['coupon_code' => 'PROMO5-PM2'])
        ->assertStatus(422)
        ->assertJsonPath('code', 'COUPON_ERROR');

    // Verify times_used is 1 (not 2)
    tenancy()->initialize($tenant);
    expect(Coupon::find($coupon->id)->times_used)->toBe(1);
    tenancy()->end();
});

// ─────────────────────────────────────────────────────────────────────────────
// PM3 — offline replay idempotent: confirmed sale cannot re-trigger coupon
// ─────────────────────────────────────────────────────────────────────────────

it('pm3_replay_confirmed_sale_does_not_double_consume_coupon', function (): void {
    [$tenant, $user, $domain] = makePmTenant('pm3', '+237601030003');
    $register = makePmRegister($tenant, 'pm3');

    tenancy()->initialize($tenant);

    $family  = Family::create(['name' => 'PM3-Famille']);
    $product = Product::create([
        'reference'     => 'PM3-REF',
        'label'         => 'PM3 Produit',
        'family_id'     => $family->id,
        'selling_price' => 50000,
        'vat_rate'      => '0.00',
        'granularity'   => Granularity::Quantity,
        'active'        => true,
    ]);

    $promotion = Promotion::create([
        'name'       => '5% coupon PM3',
        'type'       => PromotionType::Percent,
        'value'      => 500,
        'scope'      => PromotionScope::Family,
        'scope_id'   => $family->id,
        'cumulative' => true,
        'active'     => true,
    ]);

    $coupon = Coupon::create([
        'code'         => 'REPLAY-PM3',
        'promotion_id' => $promotion->id,
        'max_uses'     => 2,
        'times_used'   => 0,
    ]);

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
        'designation'              => 'PM3 Produit',
        'unit_price'               => 50000,
        'vat_rate'                 => '0.00',
        'quantity'                 => 1,
        'line_total_excluding_tax' => 50000,
        'line_total_tax'           => 0,
        'line_total_including_tax' => 50000,
        'allocations'              => [],
        'discount_amount'          => 0,
    ]);

    tenancy()->end();

    // First confirm — succeeds
    $this->actingAs($user)
        ->withHeaders(['Host' => $domain])
        ->patchJson("/commerce/sales/{$sale->id}/confirm", ['coupon_code' => 'REPLAY-PM3'])
        ->assertStatus(200);

    // Replay the same confirmed sale — returns 409 SALE_NOT_DRAFT without touching the coupon
    $this->actingAs($user)
        ->withHeaders(['Host' => $domain])
        ->patchJson("/commerce/sales/{$sale->id}/confirm", ['coupon_code' => 'REPLAY-PM3'])
        ->assertStatus(409)
        ->assertJsonPath('code', 'SALE_NOT_DRAFT');

    // Coupon incremented only once
    tenancy()->initialize($tenant);
    expect(Coupon::find($coupon->id)->times_used)->toBe(1);
    tenancy()->end();
});

// ─────────────────────────────────────────────────────────────────────────────
// PM4 — expired promotion is ignored on new sale
// ─────────────────────────────────────────────────────────────────────────────

it('pm4_expired_promotion_not_applied_to_new_sale', function (): void {
    [$tenant, $user, $domain] = makePmTenant('pm4', '+237601030004');
    $register = makePmRegister($tenant, 'pm4');

    tenancy()->initialize($tenant);

    $family  = Family::create(['name' => 'PM4-Famille']);
    $product = Product::create([
        'reference'     => 'PM4-REF',
        'label'         => 'PM4 Produit',
        'family_id'     => $family->id,
        'selling_price' => 80000,
        'vat_rate'      => '0.00',
        'granularity'   => Granularity::Quantity,
        'active'        => true,
    ]);

    Promotion::create([
        'name'     => 'Promo expirée',
        'type'     => PromotionType::Percent,
        'value'    => 2000,
        'scope'    => PromotionScope::Family,
        'scope_id' => $family->id,
        'ends_at'  => now()->subDay(),  // expired yesterday
        'active'   => true,
    ]);

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
        'designation'              => 'PM4 Produit',
        'unit_price'               => 80000,
        'vat_rate'                 => '0.00',
        'quantity'                 => 1,
        'line_total_excluding_tax' => 80000,
        'line_total_tax'           => 0,
        'line_total_including_tax' => 80000,
        'allocations'              => [],
        'discount_amount'          => 0,
    ]);

    tenancy()->end();

    $response = $this->actingAs($user)
        ->withHeaders(['Host' => $domain])
        ->patchJson("/commerce/sales/{$sale->id}/confirm")
        ->assertStatus(200);

    // No discount: expired promotion ignored
    expect($response->json('data.total_excluding_tax'))->toBe(80000);

    $lines = $response->json('data.lines');
    expect($lines[0]['discount_amount'])->toBe(0);
});

// ─────────────────────────────────────────────────────────────────────────────
// PM5 — non-cumulative promotions: best discount wins
// ─────────────────────────────────────────────────────────────────────────────

it('pm5_non_cumulative_best_discount_wins', function (): void {
    [$tenant, $user, $domain] = makePmTenant('pm5', '+237601030005');
    $register = makePmRegister($tenant, 'pm5');

    tenancy()->initialize($tenant);

    $family  = Family::create(['name' => 'PM5-Famille']);
    $product = Product::create([
        'reference'     => 'PM5-REF',
        'label'         => 'PM5 Produit',
        'family_id'     => $family->id,
        'selling_price' => 200000,
        'vat_rate'      => '0.00',
        'granularity'   => Granularity::Quantity,
        'active'        => true,
    ]);

    // P1: 10% discount (non-cumulative)
    Promotion::create([
        'name'       => '10% non-cumul',
        'type'       => PromotionType::Percent,
        'value'      => 1000,
        'scope'      => PromotionScope::Family,
        'scope_id'   => $family->id,
        'cumulative' => false,
        'active'     => true,
    ]);

    // P2: 5% discount (non-cumulative) — lower discount
    Promotion::create([
        'name'       => '5% non-cumul',
        'type'       => PromotionType::Percent,
        'value'      => 500,
        'scope'      => PromotionScope::Family,
        'scope_id'   => $family->id,
        'cumulative' => false,
        'active'     => true,
    ]);

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
        'designation'              => 'PM5 Produit',
        'unit_price'               => 200000,
        'vat_rate'                 => '0.00',
        'quantity'                 => 1,
        'line_total_excluding_tax' => 200000,
        'line_total_tax'           => 0,
        'line_total_including_tax' => 200000,
        'allocations'              => [],
        'discount_amount'          => 0,
    ]);

    tenancy()->end();

    $response = $this->actingAs($user)
        ->withHeaders(['Host' => $domain])
        ->patchJson("/commerce/sales/{$sale->id}/confirm")
        ->assertStatus(200);

    // Best: 10% of 200000 = 20000; net HT = 180000
    $lines = $response->json('data.lines');
    expect($lines[0]['discount_amount'])->toBe(20000);
    expect($response->json('data.total_excluding_tax'))->toBe(180000);
});

// ─────────────────────────────────────────────────────────────────────────────
// PM6 — Σ(line TTC) == sale.total_including_tax after discount
// ─────────────────────────────────────────────────────────────────────────────

it('pm6_sum_of_line_ttc_equals_sale_total', function (): void {
    [$tenant, $user, $domain] = makePmTenant('pm6', '+237601030006');
    $register = makePmRegister($tenant, 'pm6');

    tenancy()->initialize($tenant);

    $family   = Family::create(['name' => 'PM6-Famille']);
    $product1 = Product::create([
        'reference'     => 'PM6-REF1',
        'label'         => 'PM6 Produit 1',
        'family_id'     => $family->id,
        'selling_price' => 100000,
        'vat_rate'      => '19.25',
        'granularity'   => Granularity::Quantity,
        'active'        => true,
    ]);
    $product2 = Product::create([
        'reference'     => 'PM6-REF2',
        'label'         => 'PM6 Produit 2',
        'family_id'     => $family->id,
        'selling_price' => 50000,
        'vat_rate'      => '19.25',
        'granularity'   => Granularity::Quantity,
        'active'        => true,
    ]);

    Promotion::create([
        'name'       => '10% famille PM6',
        'type'       => PromotionType::Percent,
        'value'      => 1000,
        'scope'      => PromotionScope::Family,
        'scope_id'   => $family->id,
        'cumulative' => true,
        'active'     => true,
    ]);

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

    // Line 1: unit 100000, qty 1, gross HT = 100000, discount 10000, net HT = 90000, vat 19.25% = 17325, TTC = 107325
    SaleLine::create([
        'sale_id'                  => $sale->id,
        'product_id'               => $product1->id,
        'designation'              => 'PM6 Produit 1',
        'unit_price'               => 100000,
        'vat_rate'                 => '19.25',
        'quantity'                 => 1,
        'line_total_excluding_tax' => 100000,
        'line_total_tax'           => 19250,
        'line_total_including_tax' => 119250,
        'allocations'              => [],
        'discount_amount'          => 0,
    ]);

    // Line 2: unit 50000, qty 1, gross HT = 50000, discount 5000, net HT = 45000, vat 19.25% = 8663, TTC = 53663
    SaleLine::create([
        'sale_id'                  => $sale->id,
        'product_id'               => $product2->id,
        'designation'              => 'PM6 Produit 2',
        'unit_price'               => 50000,
        'vat_rate'                 => '19.25',
        'quantity'                 => 1,
        'line_total_excluding_tax' => 50000,
        'line_total_tax'           => 9625,
        'line_total_including_tax' => 59625,
        'allocations'              => [],
        'discount_amount'          => 0,
    ]);

    tenancy()->end();

    $response = $this->actingAs($user)
        ->withHeaders(['Host' => $domain])
        ->patchJson("/commerce/sales/{$sale->id}/confirm")
        ->assertStatus(200);

    $data  = $response->json('data');
    $lines = $data['lines'];

    $sumLineTtc = array_sum(array_column($lines, 'line_total_including_tax'));

    expect($sumLineTtc)->toBe($data['total_including_tax']);
});

// ─────────────────────────────────────────────────────────────────────────────
// PM7 — SYSCOHADA export emits account 709 debit for discount
// ─────────────────────────────────────────────────────────────────────────────

it('pm7_syscohada_export_emits_709_for_discount', function (): void {
    [$tenant, $user, $domain] = makePmTenant('pm7', '+237601030007');
    $register = makePmRegister($tenant, 'pm7');

    tenancy()->initialize($tenant);

    $family  = Family::create(['name' => 'PM7-Famille']);
    $product = Product::create([
        'reference'     => 'PM7-REF',
        'label'         => 'PM7 Produit',
        'family_id'     => $family->id,
        'selling_price' => 100000,
        'vat_rate'      => '0.00',
        'granularity'   => Granularity::Quantity,
        'active'        => true,
    ]);

    Promotion::create([
        'name'       => '15% PM7',
        'type'       => PromotionType::Percent,
        'value'      => 1500,
        'scope'      => PromotionScope::Family,
        'scope_id'   => $family->id,
        'cumulative' => true,
        'active'     => true,
    ]);

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
        'designation'              => 'PM7 Produit',
        'unit_price'               => 100000,
        'vat_rate'                 => '0.00',
        'quantity'                 => 1,
        'line_total_excluding_tax' => 100000,
        'line_total_tax'           => 0,
        'line_total_including_tax' => 100000,
        'allocations'              => [],
        'discount_amount'          => 0,
    ]);

    tenancy()->end();

    // Confirm with 15% discount
    $this->actingAs($user)
        ->withHeaders(['Host' => $domain])
        ->patchJson("/commerce/sales/{$sale->id}/confirm")
        ->assertStatus(200);

    // Now call the SYSCOHADA export service directly
    tenancy()->initialize($tenant);

    $freshSale = Sale::find($sale->id);
    $entries   = (new SyscohadaExportService())->emitForSale($freshSale);

    $accounts = array_column(
        array_map(fn ($e) => ['account_number' => $e->account_number, 'debit' => $e->debit, 'credit' => $e->credit], $entries),
        null,
        'account_number'
    );

    // 15% of 100000 = 15000 discount; gross HT = 100000; net HT = 85000; TTC = 85000 (0% VAT)
    expect($accounts)->toHaveKey('709');
    expect($accounts['709']['debit'])->toBe(15000);
    expect($accounts['701']['credit'])->toBe(100000);  // gross HT
    expect($accounts['571']['debit'])->toBe(85000);    // TTC = net TTC

    tenancy()->end();
});
