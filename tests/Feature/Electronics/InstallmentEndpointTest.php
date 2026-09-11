<?php

declare(strict_types=1);

/**
 * HTTP endpoint tests for Installment pay — Task 10.6
 *
 * !! INTENTIONNELLEMENT RED avant implémentation !!
 * Le contrôleur InstallmentController n'existe pas encore → la route
 * n'est pas enregistrée → toutes les PATCH retournent 404.
 *
 * Décisions testées :
 *   IL1 — PATCH /{installment}/pay versement non payé → 200, data.paid_at non nul
 *   IL2 — PATCH /{installment}/pay versement déjà payé → 409, code=INSTALLMENT_ALREADY_PAID
 *   IL3 — PATCH /installments/{id_inexistant}/pay → 404 (route model binding)
 *   IL4 — PATCH /{installment}/pay non authentifié → 401
 *
 * Plage téléphone makeInstTenant : +237600880001 → +237600880010
 * Tags tenant : il1, il2, …
 */

use App\Control\Domaine;
use App\Control\Tenant;
use App\Platform\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Commerce\Internal\Enums\SaleState;
use Modules\Commerce\Internal\Enums\SessionState;
use Modules\Commerce\Internal\Models\CashRegister;
use Modules\Commerce\Internal\Models\CashSession;
use Modules\Commerce\Internal\Models\OrganizationalUnit;
use Modules\Commerce\Internal\Models\PointOfSale;
use Modules\Commerce\Internal\Models\Sale;
use Modules\SecteurElectronique\Models\PaymentSchedule;
use Modules\SecteurElectronique\Services\PaymentScheduleService;

uses(RefreshDatabase::class);

// ─────────────────────────────────────────────────────────────────────────────
// Local helpers (noms UNIQUES dans toute la suite de tests)
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Crée Tenant + Domaine `il-{tag}.bsspos.cm` + User dans le tenant DB.
 *
 * @return array{0: Tenant, 1: User, 2: string}
 */
function makeInstTenant(string $tag, string $phone): array
{
    $domain = "il-{$tag}.bsspos.cm";

    $tenant = Tenant::create(['name' => "IL HTTP {$tag}", 'status' => 'actif']);
    Domaine::create(['tenant_id' => $tenant->id, 'domaine' => $domain]);

    tenancy()->initialize($tenant);

    seedTenantRolesAndPermissions();

    $user = User::create([
        'first_name' => 'IL',
        'last_name'  => 'Tester',
        'phone'      => $phone,
        'password'   => bcrypt('pass'),
        'active'     => true,
    ]);

    $user->assignRole('proprietaire');

    tenancy()->end();

    return [$tenant, $user, $domain];
}

/**
 * Ouvre le contexte tenant, crée OrganizationalUnit + PointOfSale +
 * CashRegister + CashSession + Sale (Confirmed), ferme le contexte
 * et retourne la Sale.
 */
function makeInstSale(Tenant $tenant, string $number, int $ttc): Sale
{
    tenancy()->initialize($tenant);

    $ht  = (int) round($ttc / 1.1925);
    $tax = $ttc - $ht;

    $unit     = OrganizationalUnit::create(['name' => "IL-{$number}", 'active' => true]);
    $pos      = PointOfSale::create(['name' => "IL-POS-{$number}", 'organizational_unit_id' => $unit->id, 'active' => true]);
    $register = CashRegister::create(['name' => "IL-Caisse-{$number}", 'point_of_sale_id' => $pos->id, 'active' => true]);
    $saleUser = User::create([
        'first_name' => 'IL',
        'last_name'  => 'Sale',
        'phone'      => '+237600850' . substr($number, -3),
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
        'idempotency_key'     => 'il-' . $number,
        'confirmed_at'        => now(),
    ]);

    tenancy()->end();

    return $sale;
}

/**
 * Ouvre le contexte tenant, crée un PaymentSchedule via le service
 * (pour créer les Installments associés), ferme le contexte et retourne
 * le PaymentSchedule.
 *
 * @param list<array{amount: int, due_on: string}> $installments
 */
function makeInstSchedule(Tenant $tenant, Sale $sale, int $deposit, array $installments): PaymentSchedule
{
    tenancy()->initialize($tenant);

    /** @var PaymentScheduleService $service */
    $service  = app(PaymentScheduleService::class);
    $schedule = $service->create($sale, $deposit, $installments);

    tenancy()->end();

    return $schedule;
}

// ─────────────────────────────────────────────────────────────────────────────
// IL1 — PATCH /{installment}/pay versement non payé → 200, data.paid_at non nul
// ─────────────────────────────────────────────────────────────────────────────

it('il1_pay_unpaid_installment_returns_200_with_paid_at_set', function (): void {
    [$tenant, $user, $domain] = makeInstTenant('il1', '+237600880001');
    $sale     = makeInstSale($tenant, 'IL-V-001', 200000);
    $schedule = makeInstSchedule($tenant, $sale, 0, [
        ['amount' => 200000, 'due_on' => '2026-10-01'],
    ]);

    tenancy()->initialize($tenant);
    $installmentId = $schedule->installments()->first()->id;
    tenancy()->end();

    $this->actingAs($user)
        ->withHeaders(['Host' => $domain])
        ->patchJson("/electronics/installments/{$installmentId}/pay")
        ->assertStatus(200)
        ->assertJsonPath('data.id', $installmentId)
        ->assertJsonPath('data.paid_at', fn ($v) => $v !== null);
});

// ─────────────────────────────────────────────────────────────────────────────
// IL2 — PATCH /{installment}/pay versement déjà payé → 409,
//        code=INSTALLMENT_ALREADY_PAID
// ─────────────────────────────────────────────────────────────────────────────

it('il2_pay_already_paid_installment_returns_409_already_paid', function (): void {
    [$tenant, $user, $domain] = makeInstTenant('il2', '+237600880002');
    $sale     = makeInstSale($tenant, 'IL-V-002', 200000);
    $schedule = makeInstSchedule($tenant, $sale, 0, [
        ['amount' => 200000, 'due_on' => '2026-10-01'],
    ]);

    tenancy()->initialize($tenant);
    $installmentId = $schedule->installments()->first()->id;
    tenancy()->end();

    // First call — should succeed
    $this->actingAs($user)
        ->withHeaders(['Host' => $domain])
        ->patchJson("/electronics/installments/{$installmentId}/pay")
        ->assertStatus(200);

    // Second call — should return 409
    $this->actingAs($user)
        ->withHeaders(['Host' => $domain])
        ->patchJson("/electronics/installments/{$installmentId}/pay")
        ->assertStatus(409)
        ->assertJsonPath('code', 'INSTALLMENT_ALREADY_PAID')
        ->assertJsonPath('champ', null);
});

// ─────────────────────────────────────────────────────────────────────────────
// IL3 — PATCH /installments/{id_inexistant}/pay → 404 (route model binding)
// ─────────────────────────────────────────────────────────────────────────────

it('il3_pay_nonexistent_installment_returns_404', function (): void {
    [, $user, $domain] = makeInstTenant('il3', '+237600880003');

    $ghostId = '00000000-0000-0000-0000-000000000099';

    $this->actingAs($user)
        ->withHeaders(['Host' => $domain])
        ->patchJson("/electronics/installments/{$ghostId}/pay")
        ->assertStatus(404);
});

// ─────────────────────────────────────────────────────────────────────────────
// IL4 — PATCH /{installment}/pay non authentifié → 401
// ─────────────────────────────────────────────────────────────────────────────

it('il4_pay_installment_unauthenticated_returns_401', function (): void {
    [$tenant, , $domain] = makeInstTenant('il4', '+237600880004');
    $sale     = makeInstSale($tenant, 'IL-V-004', 200000);
    $schedule = makeInstSchedule($tenant, $sale, 0, [
        ['amount' => 200000, 'due_on' => '2026-10-01'],
    ]);

    tenancy()->initialize($tenant);
    $installmentId = $schedule->installments()->first()->id;
    tenancy()->end();

    $this->withHeaders(['Host' => $domain])
        ->patchJson("/electronics/installments/{$installmentId}/pay")
        ->assertStatus(401);
});

// ─────────────────────────────────────────────────────────────────────────────
// IL5 — GET /electronics/installments → 200, paginated list (Task 11.2)
// ─────────────────────────────────────────────────────────────────────────────

it('il5_index_installments_returns_200_paginated', function (): void {
    [$tenant, $user, $domain] = makeInstTenant('il5', '+237600880005');
    $sale     = makeInstSale($tenant, 'IL-V-005', 300000);
    makeInstSchedule($tenant, $sale, 0, [
        ['amount' => 150000, 'due_on' => '2026-10-01'],
        ['amount' => 150000, 'due_on' => '2026-11-01'],
    ]);

    $this->actingAs($user)
        ->withHeaders(['Host' => $domain])
        ->getJson('/electronics/installments')
        ->assertStatus(200)
        ->assertJsonStructure(['data', 'meta'])
        ->assertJsonPath('meta.total', 2);
});

// ─────────────────────────────────────────────────────────────────────────────
// IL6 — GET /electronics/installments?payment_schedule_id= → filtered (Task 11.2)
// ─────────────────────────────────────────────────────────────────────────────

it('il6_index_installments_filtered_by_schedule', function (): void {
    [$tenant, $user, $domain] = makeInstTenant('il6', '+237600880006');

    $saleA = makeInstSale($tenant, 'IL-V-006A', 200000);
    $saleB = makeInstSale($tenant, 'IL-V-006B', 100000);

    $scheduleA = makeInstSchedule($tenant, $saleA, 0, [
        ['amount' => 100000, 'due_on' => '2026-10-01'],
        ['amount' => 100000, 'due_on' => '2026-11-01'],
    ]);
    makeInstSchedule($tenant, $saleB, 0, [
        ['amount' => 100000, 'due_on' => '2026-10-15'],
    ]);

    $this->actingAs($user)
        ->withHeaders(['Host' => $domain])
        ->getJson("/electronics/installments?payment_schedule_id={$scheduleA->id}")
        ->assertStatus(200)
        ->assertJsonPath('meta.total', 2);
});

// ─────────────────────────────────────────────────────────────────────────────
// IL7 — GET /electronics/installments non-authentifié → 401 (Task 11.2)
// ─────────────────────────────────────────────────────────────────────────────

it('il7_index_installments_unauthenticated_returns_401', function (): void {
    [, , $domain] = makeInstTenant('il7', '+237600880007');

    $this->withHeaders(['Host' => $domain])
        ->getJson('/electronics/installments')
        ->assertStatus(401);
});
