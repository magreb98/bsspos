<?php

declare(strict_types=1);

/**
 * Organizational perimeter tests — Task 0.4
 *
 * These tests are RED before the Coder's work.
 * The classes App\Platform\Identity\Models\Perimeter and User
 * and the migrations `perimeters` / `member_perimeter` do not exist yet.
 *
 * Source of truth: socle-prompt/etat/conception-0.4.md — Decisions D8, D9
 *
 * Tree structure tested:
 *   root (type='root', parent_id=NULL)
 *   └── region (type='region', parent_id=root.id)
 *       └── store (type='store', parent_id=region.id)
 *
 * SQLite architecture:
 *   The subtree() method must work with SQLite in tests
 *   (no PostgreSQL WITH RECURSIVE). The implementation may use
 *   PHP recursion in tests or a compatible SQLite query.
 *   Tests verify BEHAVIOUR, not the SQL implementation.
 */

use App\Control\Tenant;
use App\Platform\Identity\Models\Perimeter;
use App\Platform\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// ─────────────────────────────────────────────────────────────────────────────
// T6 — Subtree resolution for a perimeter
// ─────────────────────────────────────────────────────────────────────────────

it('it_resolves_the_subtree_of_a_perimeter', function (): void {
    // Arrange — tenant context with 3-level tree
    $tenant = Tenant::create(['name' => 'Tree Corp', 'status' => 'actif']);
    tenancy()->initialize($tenant);

    $root = Perimeter::create([
        'name'      => 'National HQ',
        'type'      => 'root',
        'parent_id' => null,
    ]);

    $region = Perimeter::create([
        'name'      => 'North Region',
        'type'      => 'region',
        'parent_id' => $root->id,
    ]);

    $store = Perimeter::create([
        'name'      => 'Central Store',
        'type'      => 'store',
        'parent_id' => $region->id,
    ]);

    // Act — retrieve the subtree of the region
    $subtree = $region->subtree();

    // Assert — the subtree contains the region itself and the store
    $ids = $subtree->pluck('id')->all();
    expect($ids)->toContain($region->id);
    expect($ids)->toContain($store->id);

    // Assert — the root is NOT in the region's subtree
    expect($ids)->not->toContain($root->id);

    tenancy()->end();
});

// ─────────────────────────────────────────────────────────────────────────────
// T7 — Manager visibility limited to their subtree
// ─────────────────────────────────────────────────────────────────────────────

it('it_limits_manager_visibility_to_their_subtree', function (): void {
    // Arrange — tenant context with tree + user assigned to region
    $tenant = Tenant::create(['name' => 'Vision Corp', 'status' => 'actif']);
    tenancy()->initialize($tenant);

    $root = Perimeter::create([
        'name'      => 'HQ',
        'type'      => 'root',
        'parent_id' => null,
    ]);

    $region = Perimeter::create([
        'name'      => 'South Region',
        'type'      => 'region',
        'parent_id' => $root->id,
    ]);

    $store = Perimeter::create([
        'name'      => 'South Store',
        'type'      => 'store',
        'parent_id' => $region->id,
    ]);

    // Create a user and associate them only with the `region` node
    $manager = User::create([
        'first_name' => 'Paul',
        'last_name'  => 'Manager',
        'phone'      => '+237600000030',
        'password'   => bcrypt('secret'),
    ]);

    // Association via the `member_perimeter` pivot table
    $manager->perimeters()->attach($region->id);

    // Act — retrieve perimeters visible to this manager
    $visiblePerimeters = $manager->visiblePerimeters();

    // Assert — the region and the store are visible
    expect($visiblePerimeters)->toContain($region->id);
    expect($visiblePerimeters)->toContain($store->id);

    // Assert — the root is NOT visible (manager is only assigned to region)
    expect($visiblePerimeters)->not->toContain($root->id);

    tenancy()->end();
});
