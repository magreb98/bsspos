<?php

declare(strict_types=1);

/**
 * HTTP endpoint tests for ServiceTicket resource — Task 10.4
 *
 * !! INTENTIONALLY RED !!
 * These tests are written against the real expected behaviour.  The controllers
 * are currently stubs that return ['data' => []] / ['data' => null] with HTTP
 * 200/201.  Every test below will FAIL until the full implementation
 * (controller, FormRequest, Resource, service layer, routes) is in place.
 *
 * Decisions under test:
 *   ST1  — POST valid payload → 201, data.status = 'open', data.description
 *           echoed, data.opened_at non null
 *   ST2  — POST without serial_unit_id → 422, code=VALIDATION_ERROR,
 *           champ=serial_unit_id
 *          (stub validates `required` → also returns 422, but the response
 *           body structure differs: no `code` key → assertJsonPath fails)
 *   ST3  — POST with a non-existent serial_unit_id (valid UUID) → 404,
 *           code=SERIAL_UNIT_NOT_FOUND
 *   ST4  — POST for a SerialUnit whose status = 'sold' → 422,
 *           code=SERIAL_UNIT_NOT_AVAILABLE
 *   ST5  — GET /{id} existing → 200, full field structure present
 *   ST6  — GET /{id} non-existent → 404
 *   ST7  — GET / → 200, envelope { data:[...], meta:{current_page, per_page,
 *           total, last_page} }
 *   ST8  — PATCH open → in_repair: 200, data.status = 'in_repair',
 *           data.closed_at = null
 *   ST9  — PATCH in_repair → closed: 200, data.status = 'closed',
 *           data.closed_at non null
 *   ST10 — PATCH on already-closed ticket → 409, code=TICKET_ALREADY_CLOSED
 *
 * Expected failures NOW (stub behaviour):
 *   ST1  — assertJsonPath('data.status', 'open') fails (data is [])
 *   ST2  — assertJsonPath('code', 'VALIDATION_ERROR') fails (Laravel's default
 *           422 body has no `code` key)
 *   ST3  — assertStatus(404) fails (stub returns 201)
 *   ST4  — assertStatus(422) fails (stub returns 201, no status check)
 *   ST5  — assertJsonStructure fails (data is null, fields absent)
 *   ST6  — assertStatus(404) fails (stub show() always returns 200)
 *   ST7  — assertJsonStructure(['meta' => [...]]) fails (no `meta` key)
 *   ST8  — assertJsonPath('data.status', 'in_repair') fails (data is null)
 *   ST9  — assertJsonPath('data.status', 'closed') fails (data is null)
 *   ST10 — assertStatus(409) fails (stub update() returns 200)
 *
 * Phone range : +237600850001 → +237600850020  (non-conflicting with other suites)
 * Tenant tags : st1, st2, … st10
 *
 * Helper naming (all names unique across the full test suite):
 *   makeTicketTenant()     — local; distinct from makeElectronicsHttpTenant,
 *                            makeSpecEndpointTenant, makeSerialTenant
 *   makeTicketProduct()    — local; distinct from makeElectronicsProduct,
 *                            makeElectronicProduct, makeSerialEndpointProduct
 *   makeTicketSerialUnit() — local; creates a SerialUnit inside tenant context
 */

use App\Control\Domaine;
use App\Control\Tenant;
use App\Platform\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Commerce\Internal\Enums\Granularity;
use Modules\Commerce\Internal\Models\Family;
use Modules\Commerce\Internal\Models\Product;
use Modules\SecteurElectronique\Models\SerialUnit;
use Modules\SecteurElectronique\Models\ServiceTicket;

uses(RefreshDatabase::class);

// ─────────────────────────────────────────────────────────────────────────────
// Local helpers
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Creates a Tenant + Domain in the landlord DB, then opens tenant context to
 * insert a User in the tenant DB, and returns all three.
 *
 * Name is unique: makeTicketTenant (vs makeSerialTenant, makeSpecEndpointTenant,
 * makeElectronicsHttpTenant).
 *
 * @return array{0: Tenant, 1: User, 2: string}
 */
function makeTicketTenant(string $tag, string $phone): array
{
    $domain = "ticket-{$tag}.bsspos.cm";

    $tenant = Tenant::create(['name' => "ServiceTicket HTTP {$tag}", 'status' => 'actif']);
    Domaine::create(['tenant_id' => $tenant->id, 'domaine' => $domain]);

    tenancy()->initialize($tenant);

    seedTenantRolesAndPermissions();

    $user = User::create([
        'first_name' => 'Ticket',
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
 * Opens tenant context, creates a Family + Product with Serial granularity,
 * ends the context, and returns the Product.
 *
 * Name is unique: makeTicketProduct (vs makeElectronicsProduct,
 * makeElectronicProduct, makeSerialEndpointProduct).
 */
function makeTicketProduct(Tenant $tenant, string $ref = 'ST-PROD-001'): Product
{
    tenancy()->initialize($tenant);

    $family = Family::create(['name' => "TicketFam-{$ref}", 'active' => true]);

    $product = Product::create([
        'reference'     => $ref,
        'label'         => 'Test Ticket Device',
        'family_id'     => $family->id,
        'selling_price' => 175000,
        'vat_rate'      => '19.25',
        'granularity'   => Granularity::Serial,
        'active'        => true,
    ]);

    tenancy()->end();

    return $product;
}

/**
 * Opens tenant context, creates a SerialUnit with the given status, ends the
 * context, and returns the SerialUnit.
 *
 * Name is unique: makeTicketSerialUnit (no other helper in the suite uses this
 * name).
 */
function makeTicketSerialUnit(
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

// ─────────────────────────────────────────────────────────────────────────────
// ST1 — POST valid payload → 201, status=open, description echoed,
//        opened_at non null
// ─────────────────────────────────────────────────────────────────────────────

it('st1_store_valid_payload_creates_ticket_with_open_status', function (): void {
    [$tenant, $user, $domain] = makeTicketTenant('st1', '+237600850001');
    $product = makeTicketProduct($tenant, 'ST-001');
    $unit    = makeTicketSerialUnit($tenant, $product, 'SN-ST-001');

    $this->actingAs($user)
        ->withHeaders(['Host' => $domain])
        ->postJson('/electronics/service-tickets', [
            'serial_unit_id' => $unit->id,
            'description'    => 'Screen cracked, needs replacement',
        ])
        ->assertStatus(201)
        ->assertJsonPath('data.status', 'open')
        ->assertJsonPath('data.description', 'Screen cracked, needs replacement')
        ->assertJsonMissingExact(['data' => []])  // data must not be empty stub []
        ->assertJsonPath('data.opened_at', fn ($v) => $v !== null);
});

// ─────────────────────────────────────────────────────────────────────────────
// ST2 — POST with serial_unit_id as a non-UUID string → 422,
//        code=VALIDATION_ERROR, champ=serial_unit_id
//
// The stub only checks `required` — a non-empty non-UUID string satisfies that
// rule, so the stub returns 201.  The real FormRequest adds a `uuid` rule which
// rejects this value → 422 VALIDATION_ERROR.  This makes the test genuinely RED.
// (Mirrors the SU2 pattern in SerialUnitEndpointTest.php.)
// ─────────────────────────────────────────────────────────────────────────────

it('st2_store_with_non_uuid_serial_unit_id_returns_422_validation_error', function (): void {
    [, $user, $domain] = makeTicketTenant('st2', '+237600850002');

    $this->actingAs($user)
        ->withHeaders(['Host' => $domain])
        ->postJson('/electronics/service-tickets', [
            'serial_unit_id' => 'not-a-valid-uuid',   // non-empty but not UUID format
            'description'    => 'Non-UUID serial_unit_id',
        ])
        ->assertStatus(422)
        ->assertJsonPath('code', 'VALIDATION_ERROR')
        ->assertJsonPath('champ', 'serial_unit_id');
});

// ─────────────────────────────────────────────────────────────────────────────
// ST3 — POST with non-existent serial_unit_id UUID → 404,
//        code=SERIAL_UNIT_NOT_FOUND
// ─────────────────────────────────────────────────────────────────────────────

it('st3_store_with_nonexistent_serial_unit_id_returns_404', function (): void {
    [, $user, $domain] = makeTicketTenant('st3', '+237600850003');

    $ghostId = '00000000-0000-0000-0000-000000000000';

    $this->actingAs($user)
        ->withHeaders(['Host' => $domain])
        ->postJson('/electronics/service-tickets', [
            'serial_unit_id' => $ghostId,
            'description'    => 'Unit does not exist',
        ])
        ->assertStatus(404)
        ->assertJsonPath('code', 'SERIAL_UNIT_NOT_FOUND');
});

// ─────────────────────────────────────────────────────────────────────────────
// ST4 — POST for a SerialUnit whose status = 'sold' → 422,
//        code=SERIAL_UNIT_NOT_AVAILABLE
// ─────────────────────────────────────────────────────────────────────────────

it('st4_store_for_sold_serial_unit_returns_422_not_available', function (): void {
    [$tenant, $user, $domain] = makeTicketTenant('st4', '+237600850004');
    $product  = makeTicketProduct($tenant, 'ST-004');
    $soldUnit = makeTicketSerialUnit($tenant, $product, 'SN-ST-004', 'sold');

    $this->actingAs($user)
        ->withHeaders(['Host' => $domain])
        ->postJson('/electronics/service-tickets', [
            'serial_unit_id' => $soldUnit->id,
            'description'    => 'Trying to ticket a sold unit',
        ])
        ->assertStatus(422)
        ->assertJsonPath('code', 'SERIAL_UNIT_NOT_AVAILABLE');
});

// ─────────────────────────────────────────────────────────────────────────────
// ST5 — GET /{id} existing → 200, full field structure
// ─────────────────────────────────────────────────────────────────────────────

it('st5_show_existing_ticket_returns_200_with_full_structure', function (): void {
    [$tenant, $user, $domain] = makeTicketTenant('st5', '+237600850005');
    $product = makeTicketProduct($tenant, 'ST-005');
    $unit    = makeTicketSerialUnit($tenant, $product, 'SN-ST-005');

    // Create the ticket directly (stub store does not persist)
    tenancy()->initialize($tenant);
    $ticket = ServiceTicket::create([
        'serial_unit_id' => $unit->id,
        'status'         => 'open',
        'description'    => 'Display flickering',
        'opened_at'      => now(),
    ]);
    tenancy()->end();

    $this->actingAs($user)
        ->withHeaders(['Host' => $domain])
        ->getJson("/electronics/service-tickets/{$ticket->id}")
        ->assertStatus(200)
        ->assertJsonStructure([
            'data' => [
                'id',
                'serial_unit_id',
                'status',
                'description',
                'opened_at',
                'closed_at',
                'created_at',
                'updated_at',
            ],
        ])
        ->assertJsonPath('data.id', $ticket->id)
        ->assertJsonPath('data.status', 'open')
        ->assertJsonPath('data.description', 'Display flickering');
});

// ─────────────────────────────────────────────────────────────────────────────
// ST6 — GET /{id} non-existent → 404
// ─────────────────────────────────────────────────────────────────────────────

it('st6_show_nonexistent_ticket_returns_404', function (): void {
    [, $user, $domain] = makeTicketTenant('st6', '+237600850006');

    $ghostId = '00000000-0000-0000-0000-000000000000';

    $this->actingAs($user)
        ->withHeaders(['Host' => $domain])
        ->getJson("/electronics/service-tickets/{$ghostId}")
        ->assertStatus(404);
});

// ─────────────────────────────────────────────────────────────────────────────
// ST7 — GET / → 200, envelope { data:[...], meta:{current_page, per_page,
//        total, last_page} }
// ─────────────────────────────────────────────────────────────────────────────

it('st7_index_returns_data_array_and_meta_pagination_envelope', function (): void {
    [, $user, $domain] = makeTicketTenant('st7', '+237600850007');

    $this->actingAs($user)
        ->withHeaders(['Host' => $domain])
        ->getJson('/electronics/service-tickets')
        ->assertStatus(200)
        ->assertJsonStructure([
            'data',
            'meta' => ['current_page', 'per_page', 'total', 'last_page'],
        ]);
});

// ─────────────────────────────────────────────────────────────────────────────
// ST8 — PATCH: Open → InRepair → 200, status='in_repair', closed_at=null
// ─────────────────────────────────────────────────────────────────────────────

it('st8_patch_open_to_in_repair_returns_200_with_correct_status', function (): void {
    [$tenant, $user, $domain] = makeTicketTenant('st8', '+237600850008');
    $product = makeTicketProduct($tenant, 'ST-008');
    $unit    = makeTicketSerialUnit($tenant, $product, 'SN-ST-008');

    tenancy()->initialize($tenant);
    $ticket = ServiceTicket::create([
        'serial_unit_id' => $unit->id,
        'status'         => 'open',
        'description'    => 'Keyboard not responding',
        'opened_at'      => now(),
    ]);
    tenancy()->end();

    $this->actingAs($user)
        ->withHeaders(['Host' => $domain])
        ->patchJson("/electronics/service-tickets/{$ticket->id}", [
            'status' => 'in_repair',
        ])
        ->assertStatus(200)
        ->assertJsonPath('data.status', 'in_repair')
        ->assertJsonPath('data.closed_at', null);
});

// ─────────────────────────────────────────────────────────────────────────────
// ST9 — PATCH: InRepair → Closed → 200, status='closed', closed_at non null
// ─────────────────────────────────────────────────────────────────────────────

it('st9_patch_in_repair_to_closed_returns_200_with_closed_at_set', function (): void {
    [$tenant, $user, $domain] = makeTicketTenant('st9', '+237600850009');
    $product = makeTicketProduct($tenant, 'ST-009');
    $unit    = makeTicketSerialUnit($tenant, $product, 'SN-ST-009');

    tenancy()->initialize($tenant);
    $ticket = ServiceTicket::create([
        'serial_unit_id' => $unit->id,
        'status'         => 'in_repair',
        'description'    => 'Battery swelling',
        'opened_at'      => now()->subDay(),
    ]);
    tenancy()->end();

    $this->actingAs($user)
        ->withHeaders(['Host' => $domain])
        ->patchJson("/electronics/service-tickets/{$ticket->id}", [
            'status' => 'closed',
        ])
        ->assertStatus(200)
        ->assertJsonPath('data.status', 'closed')
        ->assertJsonPath('data.closed_at', fn ($v) => $v !== null);
});

// ─────────────────────────────────────────────────────────────────────────────
// ST10 — PATCH on an already-closed ticket → 409, code=TICKET_ALREADY_CLOSED
// ─────────────────────────────────────────────────────────────────────────────

it('st10_patch_on_closed_ticket_returns_409_already_closed', function (): void {
    [$tenant, $user, $domain] = makeTicketTenant('st10', '+237600850010');
    $product = makeTicketProduct($tenant, 'ST-010');
    $unit    = makeTicketSerialUnit($tenant, $product, 'SN-ST-010');

    tenancy()->initialize($tenant);
    $ticket = ServiceTicket::create([
        'serial_unit_id' => $unit->id,
        'status'         => 'closed',
        'description'    => 'Already resolved',
        'opened_at'      => now()->subDays(3),
        'closed_at'      => now()->subDay(),
    ]);
    tenancy()->end();

    $this->actingAs($user)
        ->withHeaders(['Host' => $domain])
        ->patchJson("/electronics/service-tickets/{$ticket->id}", [
            'status' => 'in_repair',
        ])
        ->assertStatus(409)
        ->assertJsonPath('code', 'TICKET_ALREADY_CLOSED');
});
