<?php

declare(strict_types=1);

/**
 * Feature tests for perimeter auto-link and scoped queries — Task 5.5
 *
 * Acceptance criteria:
 *   - "Ouvrir une boutique sous un nœud l'ajoute d'office au périmètre du gérant"
 *   - "Un gérant de région ne voit aucune donnée hors de son périmètre"
 *
 * Decisions:
 *   D1 — OrganizationalUnit::created observer calls PerimeterLinker::link()
 *   D2 — PerimeterLinker creates a Perimeter node and sets perimeter_id on the org unit
 *   D3 — Child org unit's Perimeter has parent_id = parent org unit's perimeter_id
 *   D4 — networkBreakdownForUser filters by User::visiblePerimeters() → org units → POS
 */

use App\Control\Tenant;
use App\Platform\Identity\Models\Perimeter;
use App\Platform\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Commerce\Internal\Enums\Granularity;
use Modules\Commerce\Internal\Models\Family;
use Modules\Commerce\Internal\Models\OrganizationalUnit;
use Modules\Commerce\Internal\Models\PointOfSale;
use Modules\Commerce\Internal\Models\Product;
use Modules\Commerce\Internal\Models\StockMovement;
use Modules\Commerce\Internal\Services\NetworkStockService;

uses(RefreshDatabase::class);

// ─────────────────────────────────────────────────────────────────────────────
// Helpers
// ─────────────────────────────────────────────────────────────────────────────

function makePerimeterProduct(string $ref): Product
{
    $family = Family::firstOrCreate(['name' => 'Perimeter Family', 'active' => true]);

    return Product::create([
        'reference'     => $ref,
        'label'         => "Product {$ref}",
        'family_id'     => $family->id,
        'selling_price' => 5000,
        'vat_rate'      => '19.25',
        'granularity'   => Granularity::Quantity,
        'active'        => true,
    ]);
}

function makeManager(): User
{
    return User::create([
        'first_name' => 'Region',
        'last_name'  => 'Manager',
        'phone'      => '+237600' . rand(100000, 999999),
        'password'   => bcrypt('secret'),
    ]);
}

// ─────────────────────────────────────────────────────────────────────────────
// P1 — Creating an OrganizationalUnit auto-creates a linked Perimeter
// ─────────────────────────────────────────────────────────────────────────────

it('creating_org_unit_auto_creates_linked_perimeter', function (): void {
    $tenant = Tenant::create(['name' => 'Perimeter Corp A', 'status' => 'actif']);
    tenancy()->initialize($tenant);

    $unit = OrganizationalUnit::create(['name' => 'HQ Alpha', 'active' => true]);
    $unit->refresh();

    expect($unit->perimeter_id)->not->toBeNull();

    $perimeter = Perimeter::findOrFail($unit->perimeter_id);
    expect($perimeter->name)->toBe('HQ Alpha');
    expect($perimeter->parent_id)->toBeNull();

    tenancy()->end();
});

// ─────────────────────────────────────────────────────────────────────────────
// P2 — Child org unit's Perimeter is nested under the parent's Perimeter
// ─────────────────────────────────────────────────────────────────────────────

it('child_org_unit_perimeter_is_nested_under_parent', function (): void {
    $tenant = Tenant::create(['name' => 'Perimeter Corp B', 'status' => 'actif']);
    tenancy()->initialize($tenant);

    $parent = OrganizationalUnit::create(['name' => 'Region Beta', 'active' => true]);
    $parent->refresh();

    $child = OrganizationalUnit::create([
        'name'      => 'Store Beta',
        'parent_id' => $parent->id,
        'active'    => true,
    ]);
    $child->refresh();

    expect($child->perimeter_id)->not->toBeNull();

    $childPerimeter = Perimeter::findOrFail($child->perimeter_id);
    expect($childPerimeter->parent_id)->toBe($parent->perimeter_id);

    tenancy()->end();
});

// ─────────────────────────────────────────────────────────────────────────────
// P3 — Manager assigned to parent perimeter sees new POS under that org unit
//      Acceptance criterion: "Ouvrir une boutique sous un nœud l'ajoute
//      d'office au périmètre du gérant"
// ─────────────────────────────────────────────────────────────────────────────

it('manager_sees_pos_under_their_perimeter', function (): void {
    $tenant = Tenant::create(['name' => 'Perimeter Corp C', 'status' => 'actif']);
    tenancy()->initialize($tenant);

    $region = OrganizationalUnit::create(['name' => 'North Region', 'active' => true]);
    $region->refresh();

    $manager = makeManager();
    $manager->perimeters()->attach($region->perimeter_id);

    $pos     = PointOfSale::create(['name' => 'North Store', 'organizational_unit_id' => $region->id, 'active' => true]);
    $product = makePerimeterProduct('PRM-001');

    StockMovement::create([
        'point_of_sale_id' => $pos->id,
        'product_id'       => $product->id,
        'sale_line_id'     => null,
        'quantity'         => 50,
        'occurred_at'      => now(),
    ]);

    $svc    = new NetworkStockService();
    $result = $svc->networkBreakdownForUser($product, $manager);

    expect(count($result['pos']))->toBe(1);
    expect($result['pos'][0]['pos_name'])->toBe('North Store');
    expect($result['pos'][0]['stock'])->toBe(50);

    tenancy()->end();
});

// ─────────────────────────────────────────────────────────────────────────────
// P4 — Manager sees no data outside their perimeter
//      Acceptance criterion: "Un gérant de région ne voit aucune donnée
//      hors de son périmètre"
// ─────────────────────────────────────────────────────────────────────────────

it('manager_sees_no_data_outside_their_perimeter', function (): void {
    $tenant = Tenant::create(['name' => 'Perimeter Corp D', 'status' => 'actif']);
    tenancy()->initialize($tenant);

    $regionA = OrganizationalUnit::create(['name' => 'Region A', 'active' => true]);
    $regionB = OrganizationalUnit::create(['name' => 'Region B', 'active' => true]);
    $regionA->refresh();
    $regionB->refresh();

    $managerA = makeManager();
    $managerA->perimeters()->attach($regionA->perimeter_id);

    $posA    = PointOfSale::create(['name' => 'Store A', 'organizational_unit_id' => $regionA->id, 'active' => true]);
    $posB    = PointOfSale::create(['name' => 'Store B', 'organizational_unit_id' => $regionB->id, 'active' => true]);
    $product = makePerimeterProduct('PRM-002');

    StockMovement::create(['point_of_sale_id' => $posA->id, 'product_id' => $product->id, 'sale_line_id' => null, 'quantity' => 40, 'occurred_at' => now()]);
    StockMovement::create(['point_of_sale_id' => $posB->id, 'product_id' => $product->id, 'sale_line_id' => null, 'quantity' => 60, 'occurred_at' => now()]);

    $svc    = new NetworkStockService();
    $result = $svc->networkBreakdownForUser($product, $managerA);

    $posNames = array_column($result['pos'], 'pos_name');
    expect($posNames)->toContain('Store A');
    expect($posNames)->not->toContain('Store B');
    expect($result['total'])->toBe(40);

    tenancy()->end();
});
