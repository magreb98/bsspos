<?php

declare(strict_types=1);

/**
 * HTTP infrastructure tests for secteur-electronique routes — Task 10.1
 *
 * These tests are intentionally RED: they verify routing, authentication guard,
 * and response envelope contracts BEFORE controllers and routes are created.
 *
 * Decisions under test:
 *   H1 — /electronics/* routes are registered → unauthenticated requests return
 *         401 (not 404) for each of the 5 resource endpoints
 *   H2 — Authenticated GET /electronics/{resource} returns an envelope with
 *         a top-level "data" key (empty array allowed for an empty listing)
 *   H3 — Authenticated POST with an empty body returns a validation error
 *         envelope with exactly the keys: "code", "message", "champ"
 *   H4 — The five controller classes are autoloadable from the module namespace
 *   H5 — The electronics routes file exists in the module
 *
 * Expected failure mode NOW (before implementation):
 *   - H1 tests fail: assertStatus(401) receives 404 (route not found)
 *   - H2 tests fail: assertStatus(200) receives 404
 *   - H3 tests fail: assertStatus(422) receives 404
 *   - H4 tests fail: class_exists returns false
 *   - H5 test fails: the routes file does not exist
 *
 * Routes prefix  : /electronics
 * Middleware     : ['web', InitialiseTenant::class, 'auth']
 * Guard          : web session (no Sanctum)
 * Tenant context : resolved from Host header via InitialiseTenant (domain-based)
 */

use App\Control\Domaine;
use App\Control\Tenant;
use App\Platform\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// ─────────────────────────────────────────────────────────────────────────────
// Helper
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Creates a tenant with its domain (landlord DB) then initialises the tenant
 * context to insert a user (tenant DB) before ending the context.
 *
 * Returns [$tenant, $user, $domain] so callers can drive HTTP requests with
 * withHeaders(['Host' => $domain]) and actingAs($user).
 *
 * @return array{0: Tenant, 1: User, 2: string}
 */
function makeElectronicsHttpTenant(string $tag, string $phone): array
{
    $domain = "elec-{$tag}.bsspos.cm";

    $tenant = Tenant::create(['name' => "Electronics HTTP {$tag}", 'status' => 'actif']);
    Domaine::create(['tenant_id' => $tenant->id, 'domaine' => $domain]);

    tenancy()->initialize($tenant);

    seedTenantRolesAndPermissions();

    $user = User::create([
        'first_name' => 'Elec',
        'last_name'  => 'Tester',
        'phone'      => $phone,
        'password'   => bcrypt('pass'),
        'active'     => true,
    ]);

    $user->assignRole('proprietaire');

    tenancy()->end();

    return [$tenant, $user, $domain];
}

// ─────────────────────────────────────────────────────────────────────────────
// H1 — Unauthenticated requests return 401, not 404
// ─────────────────────────────────────────────────────────────────────────────

it('unauthenticated_get_device_specs_returns_401_not_404', function (): void {
    [, , $domain] = makeElectronicsHttpTenant('h1a', '+237600820001');

    $this->withHeaders(['Host' => $domain])
        ->getJson('/electronics/device-specs')
        ->assertStatus(401);
});

it('unauthenticated_get_serial_units_returns_401_not_404', function (): void {
    [, , $domain] = makeElectronicsHttpTenant('h1b', '+237600820002');

    $this->withHeaders(['Host' => $domain])
        ->getJson('/electronics/serial-units')
        ->assertStatus(401);
});

it('unauthenticated_get_warranties_returns_401_not_404', function (): void {
    [, , $domain] = makeElectronicsHttpTenant('h1c', '+237600820003');

    $this->withHeaders(['Host' => $domain])
        ->getJson('/electronics/warranties')
        ->assertStatus(401);
});

it('unauthenticated_get_service_tickets_returns_401_not_404', function (): void {
    [, , $domain] = makeElectronicsHttpTenant('h1d', '+237600820004');

    $this->withHeaders(['Host' => $domain])
        ->getJson('/electronics/service-tickets')
        ->assertStatus(401);
});

it('unauthenticated_get_payment_schedules_returns_401_not_404', function (): void {
    [, , $domain] = makeElectronicsHttpTenant('h1e', '+237600820005');

    $this->withHeaders(['Host' => $domain])
        ->getJson('/electronics/payment-schedules')
        ->assertStatus(401);
});

// ─────────────────────────────────────────────────────────────────────────────
// H2 — Authenticated GET listing returns envelope with "data" key
// ─────────────────────────────────────────────────────────────────────────────

it('authenticated_get_device_specs_returns_data_key', function (): void {
    [, $user, $domain] = makeElectronicsHttpTenant('h2a', '+237600820011');

    $this->actingAs($user)
        ->withHeaders(['Host' => $domain])
        ->getJson('/electronics/device-specs')
        ->assertStatus(200)
        ->assertJsonStructure(['data']);
});

it('authenticated_get_serial_units_returns_data_key', function (): void {
    [, $user, $domain] = makeElectronicsHttpTenant('h2b', '+237600820012');

    $this->actingAs($user)
        ->withHeaders(['Host' => $domain])
        ->getJson('/electronics/serial-units')
        ->assertStatus(200)
        ->assertJsonStructure(['data']);
});

it('authenticated_get_warranties_returns_data_key', function (): void {
    [, $user, $domain] = makeElectronicsHttpTenant('h2c', '+237600820013');

    $this->actingAs($user)
        ->withHeaders(['Host' => $domain])
        ->getJson('/electronics/warranties')
        ->assertStatus(200)
        ->assertJsonStructure(['data']);
});

it('authenticated_get_service_tickets_returns_data_key', function (): void {
    [, $user, $domain] = makeElectronicsHttpTenant('h2d', '+237600820014');

    $this->actingAs($user)
        ->withHeaders(['Host' => $domain])
        ->getJson('/electronics/service-tickets')
        ->assertStatus(200)
        ->assertJsonStructure(['data']);
});

it('authenticated_get_payment_schedules_returns_data_key', function (): void {
    [, $user, $domain] = makeElectronicsHttpTenant('h2e', '+237600820015');

    $this->actingAs($user)
        ->withHeaders(['Host' => $domain])
        ->getJson('/electronics/payment-schedules')
        ->assertStatus(200)
        ->assertJsonStructure(['data']);
});

// ─────────────────────────────────────────────────────────────────────────────
// H3 — Validation error envelope: keys "code", "message", "champ"
// ─────────────────────────────────────────────────────────────────────────────

it('post_device_specs_with_empty_body_returns_validation_error_envelope', function (): void {
    [, $user, $domain] = makeElectronicsHttpTenant('h3a', '+237600820021');

    $this->actingAs($user)
        ->withHeaders(['Host' => $domain])
        ->postJson('/electronics/device-specs', [])
        ->assertStatus(422)
        ->assertJsonStructure(['code', 'message', 'champ']);
});

it('post_serial_units_with_empty_body_returns_validation_error_envelope', function (): void {
    [, $user, $domain] = makeElectronicsHttpTenant('h3b', '+237600820022');

    $this->actingAs($user)
        ->withHeaders(['Host' => $domain])
        ->postJson('/electronics/serial-units', [])
        ->assertStatus(422)
        ->assertJsonStructure(['code', 'message', 'champ']);
});

it('post_warranties_with_empty_body_returns_validation_error_envelope', function (): void {
    [, $user, $domain] = makeElectronicsHttpTenant('h3c', '+237600820023');

    $this->actingAs($user)
        ->withHeaders(['Host' => $domain])
        ->postJson('/electronics/warranties', [])
        ->assertStatus(422)
        ->assertJsonStructure(['code', 'message', 'champ']);
});

it('post_service_tickets_with_empty_body_returns_validation_error_envelope', function (): void {
    [, $user, $domain] = makeElectronicsHttpTenant('h3d', '+237600820024');

    $this->actingAs($user)
        ->withHeaders(['Host' => $domain])
        ->postJson('/electronics/service-tickets', [])
        ->assertStatus(422)
        ->assertJsonStructure(['code', 'message', 'champ']);
});

it('post_payment_schedules_with_empty_body_returns_validation_error_envelope', function (): void {
    [, $user, $domain] = makeElectronicsHttpTenant('h3e', '+237600820025');

    $this->actingAs($user)
        ->withHeaders(['Host' => $domain])
        ->postJson('/electronics/payment-schedules', [])
        ->assertStatus(422)
        ->assertJsonStructure(['code', 'message', 'champ']);
});

// ─────────────────────────────────────────────────────────────────────────────
// H4 — Controller classes are autoloadable from the module namespace
// ─────────────────────────────────────────────────────────────────────────────

it('device_spec_controller_class_exists_in_module', function (): void {
    expect(class_exists('Modules\\SecteurElectronique\\Http\\Controllers\\DeviceSpecController'))
        ->toBeTrue('DeviceSpecController must be autoloadable in app-modules/secteur-electronique');
});

it('serial_unit_controller_class_exists_in_module', function (): void {
    expect(class_exists('Modules\\SecteurElectronique\\Http\\Controllers\\SerialUnitController'))
        ->toBeTrue('SerialUnitController must be autoloadable in app-modules/secteur-electronique');
});

it('warranty_controller_class_exists_in_module', function (): void {
    expect(class_exists('Modules\\SecteurElectronique\\Http\\Controllers\\WarrantyController'))
        ->toBeTrue('WarrantyController must be autoloadable in app-modules/secteur-electronique');
});

it('service_ticket_controller_class_exists_in_module', function (): void {
    expect(class_exists('Modules\\SecteurElectronique\\Http\\Controllers\\ServiceTicketController'))
        ->toBeTrue('ServiceTicketController must be autoloadable in app-modules/secteur-electronique');
});

it('payment_schedule_controller_class_exists_in_module', function (): void {
    expect(class_exists('Modules\\SecteurElectronique\\Http\\Controllers\\PaymentScheduleController'))
        ->toBeTrue('PaymentScheduleController must be autoloadable in app-modules/secteur-electronique');
});

// ─────────────────────────────────────────────────────────────────────────────
// H5 — Module routes file exists and is included in tenant routing
// ─────────────────────────────────────────────────────────────────────────────

it('electronics_routes_file_exists_in_module', function (): void {
    expect(base_path('app-modules/secteur-electronique/routes/api.php'))
        ->toBeFile('Routes file must exist at app-modules/secteur-electronique/routes/api.php');
});

it('tenant_routes_file_includes_electronics_routes', function (): void {
    $tenantRoutes = file_get_contents(base_path('routes/tenant.php'));

    expect($tenantRoutes)->toContain(
        'secteur-electronique',
        'routes/tenant.php must include the secteur-electronique routes file'
    );
});
