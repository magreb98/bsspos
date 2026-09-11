<?php

declare(strict_types=1);

/**
 * Feature tests for daily aggregates — Task 6.1
 *
 * Decisions:
 *   D1 — daily_aggregates table: (pos_id, date) unique, integer totals
 *   D2 — DailyAggregateService::recompute() — queries confirmed sales for the day
 *   D3 — Late-sync: recompute() is the same operation, no special case
 *   D4 — is_provisional = true when any open session exists for this POS on this day
 */

use App\Control\Tenant;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Commerce\Internal\Enums\Granularity;
use Modules\Commerce\Internal\Enums\SaleState;
use Modules\Commerce\Internal\Enums\SessionState;
use Modules\Commerce\Internal\Models\CashRegister;
use Modules\Commerce\Internal\Models\CashSession;
use Modules\Commerce\Internal\Models\DailyAggregate;
use Modules\Commerce\Internal\Models\Family;
use Modules\Commerce\Internal\Models\OrganizationalUnit;
use Modules\Commerce\Internal\Models\PointOfSale;
use Modules\Commerce\Internal\Models\Product;
use Modules\Commerce\Internal\Models\Sale;
use Modules\Commerce\Internal\Models\SaleLine;
use Modules\Commerce\Internal\Services\DailyAggregateService;
use Ramsey\Uuid\Uuid;

uses(RefreshDatabase::class);

// ─────────────────────────────────────────────────────────────────────────────
// Helpers
// ─────────────────────────────────────────────────────────────────────────────

function makeAggregatePos(string $name): PointOfSale
{
    $unit = OrganizationalUnit::create(['name' => "{$name} HQ", 'active' => true]);

    return PointOfSale::create(['name' => $name, 'organizational_unit_id' => $unit->id, 'active' => true]);
}

function makeAggregateSession(PointOfSale $pos, SessionState $state = SessionState::Open, ?Carbon $openedAt = null): CashSession
{
    $register = CashRegister::create(['name' => "Reg-{$pos->name}", 'point_of_sale_id' => $pos->id, 'active' => true]);

    return CashSession::create([
        'cash_register_id' => $register->id,
        'state'            => $state,
        'opened_at'        => $openedAt ?? now(),
        'opening_balance'  => 0,
        'opened_by'        => Uuid::uuid7()->toString(),
    ]);
}

function makeAggregateProduct(): Product
{
    $family = Family::firstOrCreate(['name' => 'Aggregate Family', 'active' => true]);

    return Product::create([
        'reference'     => 'AGG-' . rand(1000, 9999),
        'label'         => 'Aggregate Product',
        'family_id'     => $family->id,
        'selling_price' => 10000,
        'vat_rate'      => '19.25',
        'granularity'   => Granularity::Service,
        'active'        => true,
    ]);
}

function makeConfirmedSale(CashSession $session, Product $product, int $totalHt, int $tax, int $totalTtc, ?Carbon $confirmedAt = null): Sale
{
    $sale = Sale::create([
        'cash_session_id'     => $session->id,
        'state'               => SaleState::Confirmed,
        'number'              => 'V-2026-' . rand(1000, 9999),
        'total_excluding_tax' => $totalHt,
        'total_tax'           => $tax,
        'total_including_tax' => $totalTtc,
        'confirmed_at'        => $confirmedAt ?? now(),
        'idempotency_key'     => Uuid::uuid7()->toString(),
    ]);

    SaleLine::create([
        'sale_id'                  => $sale->id,
        'product_id'               => $product->id,
        'designation'              => $product->label,
        'unit_price'               => $totalTtc,
        'vat_rate'                 => '19.25',
        'quantity'                 => 1,
        'line_total_excluding_tax' => $totalHt,
        'line_total_tax'           => $tax,
        'line_total_including_tax' => $totalTtc,
    ]);

    return $sale;
}

// ─────────────────────────────────────────────────────────────────────────────
// A1 — recompute creates a DailyAggregate with correct totals
// ─────────────────────────────────────────────────────────────────────────────

it('recompute_creates_daily_aggregate_with_correct_totals', function (): void {
    $tenant = Tenant::create(['name' => 'Aggregate Corp A', 'status' => 'actif']);
    tenancy()->initialize($tenant);

    $pos     = makeAggregatePos('Agg Store A');
    $session = makeAggregateSession($pos);
    $product = makeAggregateProduct();
    $today   = Carbon::today();

    makeConfirmedSale($session, $product, 8383, 1617, 10000, $today);
    makeConfirmedSale($session, $product, 4188, 806, 4994, $today);

    $svc       = new DailyAggregateService();
    $aggregate = $svc->recompute($pos, $today);

    expect($aggregate->sale_count)->toBe(2);
    expect($aggregate->total_excluding_tax)->toBe(8383 + 4188);
    expect($aggregate->total_tax)->toBe(1617 + 806);
    expect($aggregate->total_including_tax)->toBe(10000 + 4994);
    expect($aggregate->computed_at)->not->toBeNull();

    tenancy()->end();
});

// ─────────────────────────────────────────────────────────────────────────────
// A2 — is_provisional = true when any session is open on that day
// ─────────────────────────────────────────────────────────────────────────────

it('is_provisional_true_when_session_is_open', function (): void {
    $tenant = Tenant::create(['name' => 'Aggregate Corp B', 'status' => 'actif']);
    tenancy()->initialize($tenant);

    $pos     = makeAggregatePos('Agg Store B');
    $today   = Carbon::today();
    makeAggregateSession($pos, SessionState::Open, $today);

    $svc       = new DailyAggregateService();
    $aggregate = $svc->recompute($pos, $today);

    expect($aggregate->is_provisional)->toBeTrue();

    tenancy()->end();
});

// ─────────────────────────────────────────────────────────────────────────────
// A3 — is_provisional = false when all sessions are closed
// ─────────────────────────────────────────────────────────────────────────────

it('is_provisional_false_when_all_sessions_closed', function (): void {
    $tenant = Tenant::create(['name' => 'Aggregate Corp C', 'status' => 'actif']);
    tenancy()->initialize($tenant);

    $pos     = makeAggregatePos('Agg Store C');
    $today   = Carbon::today();
    makeAggregateSession($pos, SessionState::Closed, $today);

    $svc       = new DailyAggregateService();
    $aggregate = $svc->recompute($pos, $today);

    expect($aggregate->is_provisional)->toBeFalse();

    tenancy()->end();
});

// ─────────────────────────────────────────────────────────────────────────────
// A4 — Late-sync: recomputing a past date after a late sale updates the aggregate
// ─────────────────────────────────────────────────────────────────────────────

it('recompute_past_date_updates_aggregate_after_late_sync', function (): void {
    $tenant = Tenant::create(['name' => 'Aggregate Corp D', 'status' => 'actif']);
    tenancy()->initialize($tenant);

    $pos      = makeAggregatePos('Agg Store D');
    $session  = makeAggregateSession($pos, SessionState::Closed, Carbon::yesterday());
    $product  = makeAggregateProduct();
    $yesterday = Carbon::yesterday();

    makeConfirmedSale($session, $product, 8383, 1617, 10000, $yesterday);

    $svc = new DailyAggregateService();
    $svc->recompute($pos, $yesterday);

    expect(DailyAggregate::where('point_of_sale_id', $pos->id)
        ->whereDate('date', $yesterday)
        ->value('sale_count'))->toBe(1);

    // Late-arriving sale for yesterday
    makeConfirmedSale($session, $product, 4188, 806, 4994, $yesterday);
    $updated = $svc->recompute($pos, $yesterday);

    expect($updated->sale_count)->toBe(2);
    expect($updated->total_including_tax)->toBe(14994);

    tenancy()->end();
});

// ─────────────────────────────────────────────────────────────────────────────
// A5 — recompute is idempotent: calling twice gives same result
// ─────────────────────────────────────────────────────────────────────────────

it('recompute_is_idempotent', function (): void {
    $tenant = Tenant::create(['name' => 'Aggregate Corp E', 'status' => 'actif']);
    tenancy()->initialize($tenant);

    $pos     = makeAggregatePos('Agg Store E');
    $session = makeAggregateSession($pos);
    $product = makeAggregateProduct();
    $today   = Carbon::today();

    makeConfirmedSale($session, $product, 8383, 1617, 10000, $today);

    $svc = new DailyAggregateService();
    $svc->recompute($pos, $today);
    $svc->recompute($pos, $today);

    expect(DailyAggregate::where('point_of_sale_id', $pos->id)
        ->whereDate('date', $today)
        ->count())->toBe(1);

    tenancy()->end();
});
