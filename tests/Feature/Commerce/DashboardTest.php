<?php

declare(strict_types=1);

/**
 * Feature tests for consolidated dashboard — Task 6.3
 *
 * Decisions:
 *   D1 — DashboardService::consolidate(User, from, to) → DashboardResult
 *   D2 — DashboardResult: totals + per-POS breakdown + isProvisional flag
 *   D3 — Perimeter-filtered: only visible POS contribute
 */

use App\Control\Tenant;
use App\Platform\Identity\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Commerce\Internal\Models\DailyAggregate;
use Modules\Commerce\Internal\Models\OrganizationalUnit;
use Modules\Commerce\Internal\Models\PointOfSale;
use Modules\Commerce\Internal\Services\DashboardService;

uses(RefreshDatabase::class);

// ─────────────────────────────────────────────────────────────────────────────
// Helpers
// ─────────────────────────────────────────────────────────────────────────────

function makeDashboardPos(string $name): PointOfSale
{
    $unit = OrganizationalUnit::create(['name' => "{$name} Unit", 'active' => true]);

    return PointOfSale::create(['name' => $name, 'organizational_unit_id' => $unit->id, 'active' => true]);
}

function makeDashboardUser(string $phone): User
{
    return User::create([
        'first_name' => 'Dashboard',
        'last_name'  => 'User',
        'phone'      => $phone,
        'password'   => bcrypt('secret'),
    ]);
}

function seedAggregate(PointOfSale $pos, string $date, int $saleCount, int $ht, int $tax, int $ttc, bool $provisional = false): void
{
    DailyAggregate::create([
        'point_of_sale_id'     => $pos->id,
        'date'                 => $date,
        'sale_count'           => $saleCount,
        'total_excluding_tax'  => $ht,
        'total_tax'            => $tax,
        'total_including_tax'  => $ttc,
        'is_provisional'       => $provisional,
        'computed_at'          => now(),
    ]);
}

// ─────────────────────────────────────────────────────────────────────────────
// C1 — Consolidate sums daily_aggregates across visible POS for a date range
// ─────────────────────────────────────────────────────────────────────────────

it('consolidate_sums_aggregates_across_visible_pos', function (): void {
    $tenant = Tenant::create(['name' => 'Dashboard Corp A', 'status' => 'actif']);
    tenancy()->initialize($tenant);

    $posA = makeDashboardPos('POS Dashboard A1');
    $posB = makeDashboardPos('POS Dashboard A2');

    $posA->load('organizationalUnit');
    $posB->load('organizationalUnit');
    $unitA = $posA->organizationalUnit ?? throw new \RuntimeException('No unit A');
    $unitB = $posB->organizationalUnit ?? throw new \RuntimeException('No unit B');

    $user = makeDashboardUser('+237600200001');
    $user->perimeters()->attach($unitA->perimeter_id);
    $user->perimeters()->attach($unitB->perimeter_id);

    seedAggregate($posA, '2026-08-01', 5, 41883, 8067, 49950);
    seedAggregate($posA, '2026-08-02', 3, 25130, 4838, 29968);
    seedAggregate($posB, '2026-08-01', 7, 58637, 11288, 69925);

    $svc    = new DashboardService();
    $result = $svc->consolidate($user, Carbon::parse('2026-08-01'), Carbon::parse('2026-08-02'));

    expect($result->totalSaleCount)->toBe(15);
    expect($result->totalIncludingTax)->toBe(49950 + 29968 + 69925);
    expect($result->isProvisional)->toBeFalse();
    expect(count($result->byPos))->toBe(2);

    tenancy()->end();
});

// ─────────────────────────────────────────────────────────────────────────────
// C2 — POS outside user's perimeter are excluded
// ─────────────────────────────────────────────────────────────────────────────

it('consolidate_excludes_pos_outside_perimeter', function (): void {
    $tenant = Tenant::create(['name' => 'Dashboard Corp B', 'status' => 'actif']);
    tenancy()->initialize($tenant);

    $posVisible  = makeDashboardPos('Visible POS B');
    $posHidden   = makeDashboardPos('Hidden POS B');

    $posVisible->load('organizationalUnit');
    $unitVisible = $posVisible->organizationalUnit ?? throw new \RuntimeException('No unit');

    $user = makeDashboardUser('+237600200002');
    $user->perimeters()->attach($unitVisible->perimeter_id);

    seedAggregate($posVisible, '2026-08-10', 10, 83765, 16124, 99889);
    seedAggregate($posHidden, '2026-08-10', 20, 167530, 32248, 199778);

    $svc    = new DashboardService();
    $result = $svc->consolidate($user, Carbon::parse('2026-08-10'), Carbon::parse('2026-08-10'));

    expect($result->totalSaleCount)->toBe(10);
    expect($result->totalIncludingTax)->toBe(99889);

    tenancy()->end();
});

// ─────────────────────────────────────────────────────────────────────────────
// C3 — isProvisional = true if any aggregate in range is provisional
// ─────────────────────────────────────────────────────────────────────────────

it('consolidate_is_provisional_when_any_aggregate_is_provisional', function (): void {
    $tenant = Tenant::create(['name' => 'Dashboard Corp C', 'status' => 'actif']);
    tenancy()->initialize($tenant);

    $pos = makeDashboardPos('Provisional POS C');
    $pos->load('organizationalUnit');
    $unit = $pos->organizationalUnit ?? throw new \RuntimeException('No unit');

    $user = makeDashboardUser('+237600200003');
    $user->perimeters()->attach($unit->perimeter_id);

    seedAggregate($pos, '2026-08-15', 3, 25130, 4838, 29968, false);
    seedAggregate($pos, '2026-08-16', 5, 41883, 8067, 49950, true);

    $svc    = new DashboardService();
    $result = $svc->consolidate($user, Carbon::parse('2026-08-15'), Carbon::parse('2026-08-16'));

    expect($result->isProvisional)->toBeTrue();

    tenancy()->end();
});

// ─────────────────────────────────────────────────────────────────────────────
// C4 — User with no perimeters gets zero result
// ─────────────────────────────────────────────────────────────────────────────

it('consolidate_returns_zero_for_user_with_no_perimeters', function (): void {
    $tenant = Tenant::create(['name' => 'Dashboard Corp D', 'status' => 'actif']);
    tenancy()->initialize($tenant);

    $user = makeDashboardUser('+237600200004');

    $svc    = new DashboardService();
    $result = $svc->consolidate($user, Carbon::parse('2026-08-01'), Carbon::parse('2026-08-31'));

    expect($result->totalSaleCount)->toBe(0);
    expect($result->totalIncludingTax)->toBe(0);
    expect($result->isProvisional)->toBeFalse();
    expect($result->byPos)->toBeEmpty();

    tenancy()->end();
});
