<?php

declare(strict_types=1);

/**
 * RBAC tests — Electronics module — Task 11.4
 *
 * Endpoints under test:
 *   GET  /electronics/device-specs        → permission:device-spec.list
 *   POST /electronics/device-specs        → permission:device-spec.write
 *   PATCH /electronics/installments/{id}/pay → permission:installment.pay
 *   POST /electronics/payment-schedules   → permission:payment-schedule.write
 *   GET  /electronics/warranties          → permission:warranty.list
 *   GET  /electronics/service-tickets     → permission:service-ticket.list
 *   GET  /electronics/payment-schedules   → permission:payment-schedule.list
 *
 * Decisions under test:
 *   RE1 — vendeur CAN GET device-specs listing (200)
 *   RE2 — vendeur CANNOT POST device-spec (403)
 *   RE3 — gérant CAN POST device-spec (201)
 *   RE4 — no-role user CANNOT GET device-specs (403)
 *   RE5 — vendeur CAN pay installment (200)
 *   RE6 — vendeur CANNOT POST payment-schedule (403)
 *   RE7 — gérant CAN POST payment-schedule (201)
 *   RE8 — vendeur CAN GET warranties (200)
 *   RE9 — vendeur CAN GET service-tickets (200)
 *   RE10 — vendeur CAN GET payment-schedules (200)
 *
 * Phone range makeErTenant : +237600940001 → +237600940010
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
use Modules\SecteurElectronique\Enums\DeviceCategory;
use Modules\SecteurElectronique\Models\PaymentSchedule;
use Modules\SecteurElectronique\Services\PaymentScheduleService;

uses(RefreshDatabase::class);

// ─────────────────────────────────────────────────────────────────────────────
// Helpers
// ─────────────────────────────────────────────────────────────────────────────

/**
 * @return array{0: Tenant, 1: User, 2: string}
 */
function makeErTenant(string $tag, string $phone, string $role): array
{
    $domain = "er-{$tag}.bsspos.cm";

    $tenant = Tenant::create(['name' => "ER RBAC {$tag}", 'status' => 'actif']);
    Domaine::create(['tenant_id' => $tenant->id, 'domaine' => $domain]);

    tenancy()->initialize($tenant);

    seedTenantRolesAndPermissions();

    $user = User::create([
        'first_name' => 'ER',
        'last_name'  => 'Tester',
        'phone'      => $phone,
        'password'   => bcrypt('pass'),
        'active'     => true,
    ]);

    if ($role !== '') {
        $user->assignRole($role);
    }

    tenancy()->end();

    return [$tenant, $user, $domain];
}

function makeErProduct(Tenant $tenant, string $suffix): Product
{
    tenancy()->initialize($tenant);

    $family  = Family::create(['name' => "ER-Famille-{$suffix}"]);
    $product = Product::create([
        'reference'     => "ER-REF-{$suffix}",
        'label'         => "ER Produit {$suffix}",
        'family_id'     => $family->id,
        'selling_price' => 200000,
        'vat_rate'      => 19.25,
        'granularity'   => Granularity::Serial,
        'active'        => true,
    ]);

    tenancy()->end();

    return $product;
}

function makeErSale(Tenant $tenant, string $number, int $ttc): Sale
{
    tenancy()->initialize($tenant);

    $ht  = (int) round($ttc / 1.1925);
    $tax = $ttc - $ht;

    $unit     = OrganizationalUnit::create(['name' => "ER-{$number}", 'active' => true]);
    $pos      = PointOfSale::create(['name' => "ER-POS-{$number}", 'organizational_unit_id' => $unit->id, 'active' => true]);
    $register = CashRegister::create(['name' => "ER-Caisse-{$number}", 'point_of_sale_id' => $pos->id, 'active' => true]);
    $saleUser = User::create([
        'first_name' => 'ER',
        'last_name'  => 'Sale',
        'phone'      => '+237699' . substr($number, -6),
        'password'   => bcrypt('x'),
        'active'     => true,
    ]);
    $session = CashSession::create([
        'cash_register_id' => $register->id,
        'state'            => SessionState::Open,
        'opened_at'        => now(),
        'opening_balance'  => 0,
        'opened_by'        => $saleUser->id,
    ]);
    $sale = Sale::create([
        'cash_session_id'     => $session->id,
        'state'               => SaleState::Confirmed,
        'number'              => $number,
        'total_excluding_tax' => $ht,
        'total_tax'           => $tax,
        'total_including_tax' => $ttc,
        'idempotency_key'     => 'er-' . $number,
        'confirmed_at'        => now(),
    ]);

    tenancy()->end();

    return $sale;
}

function makeErSchedule(Tenant $tenant, Sale $sale, int $deposit, array $installments): PaymentSchedule
{
    tenancy()->initialize($tenant);

    /** @var PaymentScheduleService $service */
    $service  = app(PaymentScheduleService::class);
    $schedule = $service->create($sale, $deposit, $installments);

    tenancy()->end();

    return $schedule;
}

// ─────────────────────────────────────────────────────────────────────────────
// RE1 — vendeur CAN list device-specs → 200
// ─────────────────────────────────────────────────────────────────────────────

it('re1_vendeur_can_list_device_specs', function (): void {
    [, $user, $domain] = makeErTenant('re1', '+237600940001', 'vendeur');

    $this->actingAs($user)
        ->withHeaders(['Host' => $domain])
        ->getJson('/electronics/device-specs')
        ->assertStatus(200)
        ->assertJsonStructure(['data']);
});

// ─────────────────────────────────────────────────────────────────────────────
// RE2 — vendeur CANNOT POST device-spec → 403
// ─────────────────────────────────────────────────────────────────────────────

it('re2_vendeur_cannot_post_device_spec', function (): void {
    [$tenant, $user, $domain] = makeErTenant('re2', '+237600940002', 'vendeur');
    $product = makeErProduct($tenant, '002');

    $this->actingAs($user)
        ->withHeaders(['Host' => $domain])
        ->postJson('/electronics/device-specs', [
            'product_id' => $product->id,
            'brand'      => 'Samsung',
            'category'   => DeviceCategory::Smartphone->value,
        ])
        ->assertStatus(403);
});

// ─────────────────────────────────────────────────────────────────────────────
// RE3 — gérant CAN POST device-spec → 201
// ─────────────────────────────────────────────────────────────────────────────

it('re3_gerant_can_post_device_spec', function (): void {
    [$tenant, $user, $domain] = makeErTenant('re3', '+237600940003', 'gérant');
    $product = makeErProduct($tenant, '003');

    $this->actingAs($user)
        ->withHeaders(['Host' => $domain])
        ->postJson('/electronics/device-specs', [
            'product_id'      => $product->id,
            'brand'           => 'Samsung',
            'category'        => DeviceCategory::Smartphone->value,
            'model'           => 'Galaxy S24 Ultra',
            'warranty_months' => 12,
        ])
        ->assertStatus(201)
        ->assertJsonPath('data.brand', 'Samsung');
});

// ─────────────────────────────────────────────────────────────────────────────
// RE4 — no-role user CANNOT list device-specs → 403
// ─────────────────────────────────────────────────────────────────────────────

it('re4_norole_user_cannot_list_device_specs', function (): void {
    [, $user, $domain] = makeErTenant('re4', '+237600940004', '');

    $this->actingAs($user)
        ->withHeaders(['Host' => $domain])
        ->getJson('/electronics/device-specs')
        ->assertStatus(403);
});

// ─────────────────────────────────────────────────────────────────────────────
// RE5 — vendeur CAN pay installment → 200
// ─────────────────────────────────────────────────────────────────────────────

it('re5_vendeur_can_pay_installment', function (): void {
    [$tenant, $user, $domain] = makeErTenant('re5', '+237600940005', 'vendeur');
    $sale     = makeErSale($tenant, 'ER-V-005', 200000);
    $schedule = makeErSchedule($tenant, $sale, 0, [
        ['amount' => 200000, 'due_on' => '2026-10-01'],
    ]);

    tenancy()->initialize($tenant);
    $installmentId = $schedule->installments()->first()->id;
    tenancy()->end();

    $this->actingAs($user)
        ->withHeaders(['Host' => $domain])
        ->patchJson("/electronics/installments/{$installmentId}/pay")
        ->assertStatus(200)
        ->assertJsonPath('data.id', $installmentId);
});

// ─────────────────────────────────────────────────────────────────────────────
// RE6 — vendeur CANNOT POST payment-schedule → 403
// ─────────────────────────────────────────────────────────────────────────────

it('re6_vendeur_cannot_post_payment_schedule', function (): void {
    [$tenant, $user, $domain] = makeErTenant('re6', '+237600940006', 'vendeur');
    $sale = makeErSale($tenant, 'ER-V-006', 200000);

    $this->actingAs($user)
        ->withHeaders(['Host' => $domain])
        ->postJson('/electronics/payment-schedules', [
            'sale_id'      => $sale->id,
            'deposit'      => 0,
            'installments' => [['amount' => 200000, 'due_on' => '2026-10-01']],
        ])
        ->assertStatus(403);
});

// ─────────────────────────────────────────────────────────────────────────────
// RE7 — gérant CAN POST payment-schedule → 201
// ─────────────────────────────────────────────────────────────────────────────

it('re7_gerant_can_post_payment_schedule', function (): void {
    [$tenant, $user, $domain] = makeErTenant('re7', '+237600940007', 'gérant');
    $sale = makeErSale($tenant, 'ER-V-007', 200000);

    $this->actingAs($user)
        ->withHeaders(['Host' => $domain])
        ->postJson('/electronics/payment-schedules', [
            'sale_id'      => $sale->id,
            'deposit'      => 0,
            'installments' => [['amount' => 200000, 'due_on' => '2026-10-01']],
        ])
        ->assertStatus(201)
        ->assertJsonPath('data.sale_id', $sale->id);
});

// ─────────────────────────────────────────────────────────────────────────────
// RE8 — vendeur CAN list warranties → 200
// ─────────────────────────────────────────────────────────────────────────────

it('re8_vendeur_can_list_warranties', function (): void {
    [, $user, $domain] = makeErTenant('re8', '+237600940008', 'vendeur');

    $this->actingAs($user)
        ->withHeaders(['Host' => $domain])
        ->getJson('/electronics/warranties')
        ->assertStatus(200)
        ->assertJsonStructure(['data']);
});

// ─────────────────────────────────────────────────────────────────────────────
// RE9 — vendeur CAN list service-tickets → 200
// ─────────────────────────────────────────────────────────────────────────────

it('re9_vendeur_can_list_service_tickets', function (): void {
    [, $user, $domain] = makeErTenant('re9', '+237600940009', 'vendeur');

    $this->actingAs($user)
        ->withHeaders(['Host' => $domain])
        ->getJson('/electronics/service-tickets')
        ->assertStatus(200)
        ->assertJsonStructure(['data']);
});

// ─────────────────────────────────────────────────────────────────────────────
// RE10 — vendeur CAN list payment-schedules → 200
// ─────────────────────────────────────────────────────────────────────────────

it('re10_vendeur_can_list_payment_schedules', function (): void {
    [, $user, $domain] = makeErTenant('re10', '+237600940010', 'vendeur');

    $this->actingAs($user)
        ->withHeaders(['Host' => $domain])
        ->getJson('/electronics/payment-schedules')
        ->assertStatus(200)
        ->assertJsonStructure(['data']);
});
