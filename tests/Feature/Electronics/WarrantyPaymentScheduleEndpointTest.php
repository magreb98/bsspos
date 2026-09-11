<?php

declare(strict_types=1);

/**
 * HTTP endpoint tests for Warranty + PaymentSchedule — Task 10.5
 *
 * !! INTENTIONNELLEMENT RED !!
 * Les contrôleurs sont des stubs qui retournent ['data' => []] / ['data' => null]
 * avec HTTP 200/201. Chaque test ci-dessous échouera jusqu'à ce que l'implémentation
 * complète (contrôleur, FormRequest, Resource, service, routes) soit en place.
 *
 * Décisions testées :
 *   W1  — POST warranty valide → 201, starts_on/expires_on non nuls, duration_months correct
 *   W2  — POST avec serial_unit_id non-UUID → 422, code=VALIDATION_ERROR, champ=serial_unit_id
 *   W3  — POST avec serial_unit_id UUID inexistant → 404, code=SERIAL_UNIT_NOT_FOUND
 *   W4  — POST avec SerialUnit status='available' (pas vendue) → 422, code=WARRANTY_UNIT_NOT_SOLD
 *   W5  — POST doublon (garantie déjà existante pour cette unité) → 409, code=WARRANTY_ALREADY_EXISTS
 *   W6  — POST avec sale_line_id UUID inexistant → 404, code=SALE_LINE_NOT_FOUND
 *   W7  — GET /warranties/{id} existant → 200, structure complète
 *   W8  — GET /warranties → 200, enveloppe {data:[...], meta:{current_page,per_page,total,last_page}}
 *   PS1 — POST payment-schedule valide → 201, data.installments présent, data.sale_id correct
 *   PS2 — POST avec sale_id non-UUID → 422, code=VALIDATION_ERROR, champ=sale_id
 *          (stub retourne 201 car `required` passe pour toute chaîne non vide; `uuid` échoue → RED)
 *   PS3 — POST avec sale_id UUID inexistant → 404, code=SALE_NOT_FOUND
 *   PS4 — POST doublon (échelonnement déjà existant pour cette vente) → 409, code=PAYMENT_SCHEDULE_ALREADY_EXISTS
 *   PS5 — POST INV-06 violation (deposit + installments ≠ total) → 422, code=PAYMENT_SCHEDULE_INVALID_TOTAL
 *   PS6 — GET /payment-schedules/{id} → 200, data contient une clé `installments` (array)
 *   PS7 — GET /payment-schedules → 200, enveloppe {data:[...], meta:{...}}
 *   PS8 — GET /payment-schedules?sale_id= → retourne seulement l'échelonnement de la vente A
 *
 * Plage téléphone helpers makeWpTenant : +237600870001 → +237600870016
 * Téléphones internes makeWpSale : suffixés par numéro de vente (ex. +237600860101)
 * Tags tenant : wp1 … wp16 (préfixe 'wp')
 * Domaines : wp-wpN.bsspos.cm
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
use Modules\SecteurElectronique\Models\PaymentSchedule;
use Modules\SecteurElectronique\Models\SerialUnit;
use Modules\SecteurElectronique\Models\Warranty;

uses(RefreshDatabase::class);

// ─────────────────────────────────────────────────────────────────────────────
// Local helpers (noms UNIQUES dans toute la suite)
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Crée Tenant + Domaine `wp-{tag}.bsspos.cm` + User dans le tenant DB.
 *
 * @return array{0: Tenant, 1: User, 2: string}
 */
function makeWpTenant(string $tag, string $phone): array
{
    $domain = "wp-{$tag}.bsspos.cm";

    $tenant = Tenant::create(['name' => "WP HTTP {$tag}", 'status' => 'actif']);
    Domaine::create(['tenant_id' => $tenant->id, 'domaine' => $domain]);

    tenancy()->initialize($tenant);

    seedTenantRolesAndPermissions();

    $user = User::create([
        'first_name' => 'WP',
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
 * Ouvre le contexte tenant, crée Family + Product (Granularity::Serial,
 * selling_price=200000), ferme le contexte et retourne le Product.
 */
function makeWpProduct(Tenant $tenant, string $ref): Product
{
    tenancy()->initialize($tenant);

    $family = Family::create(['name' => "WpFam-{$ref}", 'active' => true]);

    $product = Product::create([
        'reference'     => $ref,
        'label'         => "WP Device {$ref}",
        'family_id'     => $family->id,
        'selling_price' => 200000,
        'vat_rate'      => '19.25',
        'granularity'   => Granularity::Serial,
        'active'        => true,
    ]);

    tenancy()->end();

    return $product;
}

/**
 * Ouvre le contexte tenant, crée une SerialUnit avec le statut donné,
 * ferme le contexte et retourne la SerialUnit.
 */
function makeWpSerialUnit(
    Tenant  $tenant,
    Product $product,
    string  $serial,
    string  $status = 'available',
): SerialUnit {
    tenancy()->initialize($tenant);

    $unit = SerialUnit::create([
        'product_id'    => $product->id,
        'serial_number' => $serial,
        'status'        => $status,
    ]);

    tenancy()->end();

    return $unit;
}

/**
 * Ouvre le contexte tenant, crée OrganizationalUnit + PointOfSale +
 * CashRegister + User + CashSession + Sale (Confirmed), ferme le contexte
 * et retourne la Sale.
 */
function makeWpSale(Tenant $tenant, string $number, int $ttc): Sale
{
    tenancy()->initialize($tenant);

    $ht  = (int) round($ttc / 1.1925);
    $tax = $ttc - $ht;

    $unit     = OrganizationalUnit::create(['name' => "WP-{$number}", 'active' => true]);
    $pos      = PointOfSale::create(['name' => "WP-POS-{$number}", 'organizational_unit_id' => $unit->id, 'active' => true]);
    $register = CashRegister::create(['name' => "WP-Caisse-{$number}", 'point_of_sale_id' => $pos->id, 'active' => true]);
    $user     = User::create([
        'first_name' => 'WP',
        'last_name'  => 'Sale',
        'phone'      => '+237600860' . substr($number, -3),
        'password'   => bcrypt('x'),
        'active'     => true,
    ]);
    $session = CashSession::create([
        'cash_register_id' => $register->id,
        'state'            => SessionState::Open,
        'opened_at'        => now(),
        'opening_balance'  => 0,
        'opened_by'        => $user->id,
    ]);
    $sale = Sale::create([
        'cash_session_id'     => $session->id,
        'state'               => SaleState::Confirmed,
        'number'              => $number,
        'total_excluding_tax' => $ht,
        'total_tax'           => $tax,
        'total_including_tax' => $ttc,
        'idempotency_key'     => 'wp-' . $number,
        'confirmed_at'        => now(),
    ]);

    tenancy()->end();

    return $sale;
}

/**
 * Ouvre le contexte tenant, crée une SaleLine pour la sale et le product,
 * ferme le contexte et retourne la SaleLine.
 */
function makeWpSaleLine(Tenant $tenant, Sale $sale, Product $product): SaleLine
{
    tenancy()->initialize($tenant);

    $line = SaleLine::create([
        'sale_id'                  => $sale->id,
        'product_id'               => $product->id,
        'designation'              => 'WP Device',
        'unit_price'               => 200000,
        'vat_rate'                 => '19.25',
        'quantity'                 => 1,
        'line_total_excluding_tax' => 167782,
        'line_total_tax'           => 32218,
        'line_total_including_tax' => 200000,
    ]);

    tenancy()->end();

    return $line;
}

// ─────────────────────────────────────────────────────────────────────────────
// W1 — POST warranty valide → 201, starts_on/expires_on non nuls, duration_months correct
// ─────────────────────────────────────────────────────────────────────────────

it('w1_store_valid_warranty_returns_201_with_dates_and_duration', function (): void {
    [$tenant, $user, $domain] = makeWpTenant('wp1', '+237600870001');
    $product = makeWpProduct($tenant, 'WP-PROD-W1');
    $unit    = makeWpSerialUnit($tenant, $product, 'SN-W1', 'sold');
    $sale    = makeWpSale($tenant, 'WP-V-101', 200000);
    $line    = makeWpSaleLine($tenant, $sale, $product);

    $this->actingAs($user)
        ->withHeaders(['Host' => $domain])
        ->postJson('/electronics/warranties', [
            'serial_unit_id'  => $unit->id,
            'sale_line_id'    => $line->id,
            'duration_months' => 12,
        ])
        ->assertStatus(201)
        ->assertJsonPath('data.starts_on', fn ($v) => $v !== null)
        ->assertJsonPath('data.expires_on', fn ($v) => $v !== null)
        ->assertJsonPath('data.duration_months', 12);
});

// ─────────────────────────────────────────────────────────────────────────────
// W2 — POST avec serial_unit_id non-UUID → 422, code=VALIDATION_ERROR, champ=serial_unit_id
//      (Le stub valide uniquement `required` → une chaîne non-UUID non vide passe → 201;
//       le vrai FormRequest ajoute la règle `uuid` → 422 → genuinement RED)
// ─────────────────────────────────────────────────────────────────────────────

it('w2_store_non_uuid_serial_unit_id_returns_422_validation_error', function (): void {
    [, $user, $domain] = makeWpTenant('wp2', '+237600870002');

    $this->actingAs($user)
        ->withHeaders(['Host' => $domain])
        ->postJson('/electronics/warranties', [
            'serial_unit_id'  => 'not-a-valid-uuid',
            'sale_line_id'    => '00000000-0000-0000-0000-000000000000',
            'duration_months' => 12,
        ])
        ->assertStatus(422)
        ->assertJsonPath('code', 'VALIDATION_ERROR')
        ->assertJsonPath('champ', 'serial_unit_id');
});

// ─────────────────────────────────────────────────────────────────────────────
// W3 — POST avec serial_unit_id UUID inexistant → 404, code=SERIAL_UNIT_NOT_FOUND
// ─────────────────────────────────────────────────────────────────────────────

it('w3_store_nonexistent_serial_unit_id_returns_404_serial_unit_not_found', function (): void {
    [, $user, $domain] = makeWpTenant('wp3', '+237600870003');

    $ghostId = '00000000-0000-0000-0000-000000000001';

    $this->actingAs($user)
        ->withHeaders(['Host' => $domain])
        ->postJson('/electronics/warranties', [
            'serial_unit_id'  => $ghostId,
            'sale_line_id'    => '00000000-0000-0000-0000-000000000000',
            'duration_months' => 12,
        ])
        ->assertStatus(404)
        ->assertJsonPath('code', 'SERIAL_UNIT_NOT_FOUND');
});

// ─────────────────────────────────────────────────────────────────────────────
// W4 — POST avec SerialUnit status='available' → 422, code=WARRANTY_UNIT_NOT_SOLD
// ─────────────────────────────────────────────────────────────────────────────

it('w4_store_for_available_serial_unit_returns_422_warranty_unit_not_sold', function (): void {
    [$tenant, $user, $domain] = makeWpTenant('wp4', '+237600870004');
    $product     = makeWpProduct($tenant, 'WP-PROD-W4');
    $availUnit   = makeWpSerialUnit($tenant, $product, 'SN-W4', 'available');

    $this->actingAs($user)
        ->withHeaders(['Host' => $domain])
        ->postJson('/electronics/warranties', [
            'serial_unit_id'  => $availUnit->id,
            'sale_line_id'    => '00000000-0000-0000-0000-000000000000',
            'duration_months' => 12,
        ])
        ->assertStatus(422)
        ->assertJsonPath('code', 'WARRANTY_UNIT_NOT_SOLD');
});

// ─────────────────────────────────────────────────────────────────────────────
// W5 — POST doublon → 409, code=WARRANTY_ALREADY_EXISTS
// ─────────────────────────────────────────────────────────────────────────────

it('w5_store_duplicate_warranty_returns_409_warranty_already_exists', function (): void {
    [$tenant, $user, $domain] = makeWpTenant('wp5', '+237600870005');
    $product = makeWpProduct($tenant, 'WP-PROD-W5');
    $unit    = makeWpSerialUnit($tenant, $product, 'SN-W5', 'sold');
    $sale    = makeWpSale($tenant, 'WP-V-501', 200000);
    $line    = makeWpSaleLine($tenant, $sale, $product);

    // Créer la garantie directement en DB pour simuler l'existant
    tenancy()->initialize($tenant);
    Warranty::create([
        'serial_unit_id'  => $unit->id,
        'sale_line_id'    => $line->id,
        'starts_on'       => now()->toDateString(),
        'expires_on'      => now()->addMonths(12)->toDateString(),
        'duration_months' => 12,
    ]);
    tenancy()->end();

    // Tentative de création d'une seconde garantie pour la même unité
    $this->actingAs($user)
        ->withHeaders(['Host' => $domain])
        ->postJson('/electronics/warranties', [
            'serial_unit_id'  => $unit->id,
            'sale_line_id'    => $line->id,
            'duration_months' => 24,
        ])
        ->assertStatus(409)
        ->assertJsonPath('code', 'WARRANTY_ALREADY_EXISTS');
});

// ─────────────────────────────────────────────────────────────────────────────
// W6 — POST avec sale_line_id UUID inexistant → 404, code=SALE_LINE_NOT_FOUND
// ─────────────────────────────────────────────────────────────────────────────

it('w6_store_nonexistent_sale_line_id_returns_404_sale_line_not_found', function (): void {
    [$tenant, $user, $domain] = makeWpTenant('wp6', '+237600870006');
    $product = makeWpProduct($tenant, 'WP-PROD-W6');
    $unit    = makeWpSerialUnit($tenant, $product, 'SN-W6', 'sold');

    $ghostLineId = '00000000-0000-0000-0000-000000000000';

    $this->actingAs($user)
        ->withHeaders(['Host' => $domain])
        ->postJson('/electronics/warranties', [
            'serial_unit_id'  => $unit->id,
            'sale_line_id'    => $ghostLineId,
            'duration_months' => 12,
        ])
        ->assertStatus(404)
        ->assertJsonPath('code', 'SALE_LINE_NOT_FOUND');
});

// ─────────────────────────────────────────────────────────────────────────────
// W7 — GET /warranties/{id} existant → 200, structure complète
// ─────────────────────────────────────────────────────────────────────────────

it('w7_show_existing_warranty_returns_200_with_full_structure', function (): void {
    [$tenant, $user, $domain] = makeWpTenant('wp7', '+237600870007');
    $product = makeWpProduct($tenant, 'WP-PROD-W7');
    $unit    = makeWpSerialUnit($tenant, $product, 'SN-W7', 'sold');
    $sale    = makeWpSale($tenant, 'WP-V-701', 200000);
    $line    = makeWpSaleLine($tenant, $sale, $product);

    tenancy()->initialize($tenant);
    $warranty = Warranty::create([
        'serial_unit_id'  => $unit->id,
        'sale_line_id'    => $line->id,
        'starts_on'       => '2026-09-01',
        'expires_on'      => '2027-09-01',
        'duration_months' => 12,
    ]);
    tenancy()->end();

    $this->actingAs($user)
        ->withHeaders(['Host' => $domain])
        ->getJson("/electronics/warranties/{$warranty->id}")
        ->assertStatus(200)
        ->assertJsonStructure([
            'data' => [
                'id',
                'serial_unit_id',
                'sale_line_id',
                'starts_on',
                'expires_on',
                'duration_months',
                'created_at',
                'updated_at',
            ],
        ])
        ->assertJsonPath('data.id', $warranty->id)
        ->assertJsonPath('data.duration_months', 12);
});

// ─────────────────────────────────────────────────────────────────────────────
// W8 — GET /warranties → 200, enveloppe {data:[...], meta:{...}}
// ─────────────────────────────────────────────────────────────────────────────

it('w8_index_warranties_returns_data_array_and_meta_pagination_envelope', function (): void {
    [, $user, $domain] = makeWpTenant('wp8', '+237600870008');

    $this->actingAs($user)
        ->withHeaders(['Host' => $domain])
        ->getJson('/electronics/warranties')
        ->assertStatus(200)
        ->assertJsonStructure([
            'data',
            'meta' => ['current_page', 'per_page', 'total', 'last_page'],
        ]);
});

// ─────────────────────────────────────────────────────────────────────────────
// PS1 — POST payment-schedule valide → 201, data.installments présent, data.sale_id correct
// ─────────────────────────────────────────────────────────────────────────────

it('ps1_store_valid_payment_schedule_returns_201_with_installments_and_sale_id', function (): void {
    [$tenant, $user, $domain] = makeWpTenant('wp9', '+237600870009');
    $sale = makeWpSale($tenant, 'WP-V-901', 300000);

    $this->actingAs($user)
        ->withHeaders(['Host' => $domain])
        ->postJson('/electronics/payment-schedules', [
            'sale_id'      => $sale->id,
            'deposit'      => 100000,
            'installments' => [
                ['amount' => 100000, 'due_on' => '2026-10-01'],
                ['amount' => 100000, 'due_on' => '2026-11-01'],
            ],
        ])
        ->assertStatus(201)
        ->assertJsonPath('data.sale_id', $sale->id)
        ->assertJsonPath('data.installments', fn ($v) => is_array($v));
});

// ─────────────────────────────────────────────────────────────────────────────
// PS2 — POST avec sale_id non-UUID → 422, code=VALIDATION_ERROR, champ=sale_id
//        (Le stub valide uniquement `required` → une chaîne non-UUID non vide passe → 201;
//         le vrai FormRequest ajoute la règle `uuid` → 422 → genuinement RED.
//         Miroir du pattern SU2 / ST2.)
// ─────────────────────────────────────────────────────────────────────────────

it('ps2_store_non_uuid_sale_id_returns_422_validation_error_on_sale_id', function (): void {
    [, $user, $domain] = makeWpTenant('wp10', '+237600870010');

    $this->actingAs($user)
        ->withHeaders(['Host' => $domain])
        ->postJson('/electronics/payment-schedules', [
            'sale_id'      => 'not-a-valid-uuid',
            'deposit'      => 100000,
            'installments' => [
                ['amount' => 100000, 'due_on' => '2026-10-01'],
            ],
        ])
        ->assertStatus(422)
        ->assertJsonPath('code', 'VALIDATION_ERROR')
        ->assertJsonPath('champ', 'sale_id');
});

// ─────────────────────────────────────────────────────────────────────────────
// PS3 — POST avec sale_id UUID inexistant → 404, code=SALE_NOT_FOUND
// ─────────────────────────────────────────────────────────────────────────────

it('ps3_store_nonexistent_sale_id_returns_404_sale_not_found', function (): void {
    [, $user, $domain] = makeWpTenant('wp11', '+237600870011');

    $ghostSaleId = '00000000-0000-0000-0000-000000000000';

    $this->actingAs($user)
        ->withHeaders(['Host' => $domain])
        ->postJson('/electronics/payment-schedules', [
            'sale_id'      => $ghostSaleId,
            'deposit'      => 100000,
            'installments' => [
                ['amount' => 100000, 'due_on' => '2026-10-01'],
            ],
        ])
        ->assertStatus(404)
        ->assertJsonPath('code', 'SALE_NOT_FOUND');
});

// ─────────────────────────────────────────────────────────────────────────────
// PS4 — POST doublon → 409, code=PAYMENT_SCHEDULE_ALREADY_EXISTS
// ─────────────────────────────────────────────────────────────────────────────

it('ps4_store_duplicate_payment_schedule_returns_409_already_exists', function (): void {
    [$tenant, $user, $domain] = makeWpTenant('wp12', '+237600870012');
    $sale = makeWpSale($tenant, 'WP-V-121', 300000);

    // Créer l'échelonnement directement en DB
    tenancy()->initialize($tenant);
    PaymentSchedule::create([
        'sale_id' => $sale->id,
        'deposit' => 100000,
        'total'   => 300000,
    ]);
    tenancy()->end();

    // Tentative de création d'un second échelonnement pour la même vente
    $this->actingAs($user)
        ->withHeaders(['Host' => $domain])
        ->postJson('/electronics/payment-schedules', [
            'sale_id'      => $sale->id,
            'deposit'      => 100000,
            'installments' => [
                ['amount' => 100000, 'due_on' => '2026-10-01'],
                ['amount' => 100000, 'due_on' => '2026-11-01'],
            ],
        ])
        ->assertStatus(409)
        ->assertJsonPath('code', 'PAYMENT_SCHEDULE_ALREADY_EXISTS');
});

// ─────────────────────────────────────────────────────────────────────────────
// PS5 — POST INV-06 violation (deposit + installments ≠ total) → 422,
//        code=PAYMENT_SCHEDULE_INVALID_TOTAL
//        Sale total=300000, deposit=50000, installments=[{amount:50000}] → 100000 ≠ 300000
// ─────────────────────────────────────────────────────────────────────────────

it('ps5_store_with_imbalanced_amounts_returns_422_invalid_total', function (): void {
    [$tenant, $user, $domain] = makeWpTenant('wp13', '+237600870013');
    $sale = makeWpSale($tenant, 'WP-V-131', 300000);

    $this->actingAs($user)
        ->withHeaders(['Host' => $domain])
        ->postJson('/electronics/payment-schedules', [
            'sale_id'      => $sale->id,
            'deposit'      => 50000,
            'installments' => [
                ['amount' => 50000, 'due_on' => '2026-10-01'],
                // 50000 + 50000 = 100000 ≠ 300000 → violation INV-06
            ],
        ])
        ->assertStatus(422)
        ->assertJsonPath('code', 'PAYMENT_SCHEDULE_INVALID_TOTAL');
});

// ─────────────────────────────────────────────────────────────────────────────
// PS6 — GET /payment-schedules/{id} → 200, data contient une clé `installments` (array)
// ─────────────────────────────────────────────────────────────────────────────

it('ps6_show_existing_payment_schedule_returns_200_with_installments_key', function (): void {
    [$tenant, $user, $domain] = makeWpTenant('wp14', '+237600870014');
    $sale = makeWpSale($tenant, 'WP-V-141', 200000);

    tenancy()->initialize($tenant);
    $schedule = PaymentSchedule::create([
        'sale_id' => $sale->id,
        'deposit' => 100000,
        'total'   => 200000,
    ]);
    tenancy()->end();

    $this->actingAs($user)
        ->withHeaders(['Host' => $domain])
        ->getJson("/electronics/payment-schedules/{$schedule->id}")
        ->assertStatus(200)
        ->assertJsonPath('data.installments', fn ($v) => is_array($v));
});

// ─────────────────────────────────────────────────────────────────────────────
// PS7 — GET /payment-schedules → 200, enveloppe {data:[...], meta:{...}}
// ─────────────────────────────────────────────────────────────────────────────

it('ps7_index_payment_schedules_returns_data_array_and_meta_envelope', function (): void {
    [, $user, $domain] = makeWpTenant('wp15', '+237600870015');

    $this->actingAs($user)
        ->withHeaders(['Host' => $domain])
        ->getJson('/electronics/payment-schedules')
        ->assertStatus(200)
        ->assertJsonStructure([
            'data',
            'meta' => ['current_page', 'per_page', 'total', 'last_page'],
        ]);
});

// ─────────────────────────────────────────────────────────────────────────────
// PS8 — GET /payment-schedules?sale_id= → retourne seulement l'échelonnement
//        de la vente A, pas celui de la vente B
// ─────────────────────────────────────────────────────────────────────────────

it('ps8_index_filtered_by_sale_id_returns_only_matching_schedule', function (): void {
    [$tenant, $user, $domain] = makeWpTenant('wp16', '+237600870016');
    $saleA = makeWpSale($tenant, 'WP-V-161', 200000);
    $saleB = makeWpSale($tenant, 'WP-V-162', 300000);

    // Créer les deux échelonnements directement en DB
    tenancy()->initialize($tenant);
    $scheduleA = PaymentSchedule::create([
        'sale_id' => $saleA->id,
        'deposit' => 100000,
        'total'   => 200000,
    ]);
    PaymentSchedule::create([
        'sale_id' => $saleB->id,
        'deposit' => 100000,
        'total'   => 300000,
    ]);
    tenancy()->end();

    // Filtrer par sale_id de la vente A — un seul résultat attendu
    $response = $this->actingAs($user)
        ->withHeaders(['Host' => $domain])
        ->getJson("/electronics/payment-schedules?sale_id={$saleA->id}")
        ->assertStatus(200)
        ->assertJsonStructure(['data', 'meta']);

    $data = $response->json('data');
    expect($data)->toHaveCount(1)
        ->and($data[0]['sale_id'])->toBe($saleA->id);
});
