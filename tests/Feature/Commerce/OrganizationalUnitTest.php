<?php

declare(strict_types=1);

/**
 * Feature tests for commerce module foundation — Task 1.1
 *
 * Decisions:
 *   D2 — organizational_units: uuid PK, name, parent_id (self-ref nullable), active
 *   D3 — points_of_sale: uuid PK, organizational_unit_id FK, name, active
 *   D4 — cash_registers: uuid PK, point_of_sale_id FK, name, active
 *   D5 — Eloquent models in Modules\Commerce\Internal\Models\
 */

use App\Control\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Modules\Commerce\Internal\Models\OrganizationalUnit;
use Modules\Commerce\Internal\Models\PointOfSale;

uses(RefreshDatabase::class);

// ─────────────────────────────────────────────────────────────────────────────
// T1 — organizational_units table exists with required columns
// ─────────────────────────────────────────────────────────────────────────────

it('organizational_units_table_has_required_columns', function (): void {
    $tenant = Tenant::create(['name' => 'Schema Corp', 'status' => 'actif']);
    tenancy()->initialize($tenant);

    expect(Schema::hasTable('organizational_units'))->toBeTrue();
    expect(Schema::hasColumns('organizational_units', ['id', 'name', 'parent_id', 'active']))->toBeTrue();

    tenancy()->end();
});

// ─────────────────────────────────────────────────────────────────────────────
// T2 — points_of_sale table has required columns
// ─────────────────────────────────────────────────────────────────────────────

it('points_of_sale_table_has_required_columns', function (): void {
    $tenant = Tenant::create(['name' => 'POS Corp', 'status' => 'actif']);
    tenancy()->initialize($tenant);

    expect(Schema::hasTable('points_of_sale'))->toBeTrue();
    expect(Schema::hasColumns('points_of_sale', ['id', 'organizational_unit_id', 'name', 'active']))->toBeTrue();

    tenancy()->end();
});

// ─────────────────────────────────────────────────────────────────────────────
// T3 — cash_registers table has required columns
// ─────────────────────────────────────────────────────────────────────────────

it('cash_registers_table_has_required_columns', function (): void {
    $tenant = Tenant::create(['name' => 'Register Corp', 'status' => 'actif']);
    tenancy()->initialize($tenant);

    expect(Schema::hasTable('cash_registers'))->toBeTrue();
    expect(Schema::hasColumns('cash_registers', ['id', 'point_of_sale_id', 'name', 'active']))->toBeTrue();

    tenancy()->end();
});

// ─────────────────────────────────────────────────────────────────────────────
// T4 — OrganizationalUnit::parent() returns the parent
// ─────────────────────────────────────────────────────────────────────────────

it('organizational_unit_parent_relation_works', function (): void {
    $tenant = Tenant::create(['name' => 'Relations Corp', 'status' => 'actif']);
    tenancy()->initialize($tenant);

    $root  = OrganizationalUnit::create(['name' => 'Douala']);
    $child = OrganizationalUnit::create(['name' => 'Bonanjo', 'parent_id' => $root->id]);

    expect(($child->parent ?? throw new \DomainException('No parent.'))->id)->toBe($root->id);

    tenancy()->end();
});

// ─────────────────────────────────────────────────────────────────────────────
// T5 — OrganizationalUnit::children() returns child collection
// ─────────────────────────────────────────────────────────────────────────────

it('organizational_unit_children_relation_works', function (): void {
    $tenant = Tenant::create(['name' => 'Children Corp', 'status' => 'actif']);
    tenancy()->initialize($tenant);

    $root   = OrganizationalUnit::create(['name' => 'Yaoundé']);
    $child1 = OrganizationalUnit::create(['name' => 'Centre', 'parent_id' => $root->id]);
    $child2 = OrganizationalUnit::create(['name' => 'Nlongkak', 'parent_id' => $root->id]);

    $children = $root->children;

    expect($children)->toHaveCount(2);
    expect($children->pluck('id')->sort()->values()->toArray())
        ->toBe(collect([$child1->id, $child2->id])->sort()->values()->toArray());

    tenancy()->end();
});

// ─────────────────────────────────────────────────────────────────────────────
// T7 — PointOfSale::organizationalUnit() returns the unit
// ─────────────────────────────────────────────────────────────────────────────

it('point_of_sale_belongs_to_organizational_unit', function (): void {
    $tenant = Tenant::create(['name' => 'POS Relations Corp', 'status' => 'actif']);
    tenancy()->initialize($tenant);

    $unit = OrganizationalUnit::create(['name' => 'Akwa']);
    $pos  = PointOfSale::create([
        'name'                   => 'Boutique Akwa',
        'organizational_unit_id' => $unit->id,
    ]);

    expect(($pos->organizationalUnit ?? throw new \DomainException('No unit.'))->id)->toBe($unit->id);

    tenancy()->end();
});
