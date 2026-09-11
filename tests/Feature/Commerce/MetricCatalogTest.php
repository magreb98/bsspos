<?php

declare(strict_types=1);

/**
 * Feature tests for the metrics catalog with permission control — Task 6.2
 *
 * Decisions:
 *   D1 — MetricDefinition(name, permission, label)
 *   D2 — MetricCatalog::visibleFor(User) returns only permitted metrics
 *   D3 — MetricGuard::authorize throws DomainException on unknown or forbidden metrics
 */

use App\Control\Tenant;
use App\Platform\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Commerce\Internal\Metrics\MetricCatalog;
use Modules\Commerce\Internal\Metrics\MetricDefinition;
use Modules\Commerce\Internal\Metrics\MetricGuard;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

// ─────────────────────────────────────────────────────────────────────────────
// Helpers
// ─────────────────────────────────────────────────────────────────────────────

function buildCatalog(): MetricCatalog
{
    $catalog = new MetricCatalog();
    $catalog->register(new MetricDefinition('revenue.daily.total', 'metrics.revenue.view', 'CA du jour'));
    $catalog->register(new MetricDefinition('stock.network.total', 'metrics.stock.view', 'Stock réseau'));

    return $catalog;
}

function makeMetricUser(string $phone): User
{
    return User::create([
        'first_name' => 'Test',
        'last_name'  => 'User',
        'phone'      => $phone,
        'password'   => bcrypt('secret'),
    ]);
}

// ─────────────────────────────────────────────────────────────────────────────
// M1 — User WITH permission can authorize a metric
// ─────────────────────────────────────────────────────────────────────────────

it('authorized_user_can_access_metric', function (): void {
    $tenant = Tenant::create(['name' => 'Metric Corp A', 'status' => 'actif']);
    tenancy()->initialize($tenant);

    $permission = Permission::findOrCreate('metrics.revenue.view', 'web');
    $user       = makeMetricUser('+237600000101');
    $user->givePermissionTo($permission);

    $catalog = buildCatalog();
    $guard   = new MetricGuard($catalog);

    $definition = $guard->authorize('revenue.daily.total', $user);

    expect($definition->name)->toBe('revenue.daily.total');

    tenancy()->end();
});

// ─────────────────────────────────────────────────────────────────────────────
// M2 — User WITHOUT permission throws DomainException
// ─────────────────────────────────────────────────────────────────────────────

it('unauthorized_user_cannot_access_metric', function (): void {
    $tenant = Tenant::create(['name' => 'Metric Corp B', 'status' => 'actif']);
    tenancy()->initialize($tenant);

    Permission::findOrCreate('metrics.revenue.view', 'web');
    $user = makeMetricUser('+237600000102');

    $catalog = buildCatalog();
    $guard   = new MetricGuard($catalog);

    expect(fn () => $guard->authorize('revenue.daily.total', $user))
        ->toThrow(\DomainException::class);

    tenancy()->end();
});

// ─────────────────────────────────────────────────────────────────────────────
// M3 — Unknown metric name throws DomainException
// ─────────────────────────────────────────────────────────────────────────────

it('unknown_metric_throws_domain_exception', function (): void {
    $tenant = Tenant::create(['name' => 'Metric Corp C', 'status' => 'actif']);
    tenancy()->initialize($tenant);

    $user    = makeMetricUser('+237600000103');
    $catalog = buildCatalog();
    $guard   = new MetricGuard($catalog);

    expect(fn () => $guard->authorize('nonexistent.metric', $user))
        ->toThrow(\DomainException::class, 'Unknown metric');

    tenancy()->end();
});

// ─────────────────────────────────────────────────────────────────────────────
// M4 — visibleFor returns only metrics the user's permissions allow
// ─────────────────────────────────────────────────────────────────────────────

it('visible_for_returns_only_permitted_metrics', function (): void {
    $tenant = Tenant::create(['name' => 'Metric Corp D', 'status' => 'actif']);
    tenancy()->initialize($tenant);

    $revenuePermission = Permission::findOrCreate('metrics.revenue.view', 'web');
    Permission::findOrCreate('metrics.stock.view', 'web');

    $user = makeMetricUser('+237600000104');
    $user->givePermissionTo($revenuePermission);

    $catalog = buildCatalog();
    $visible = $catalog->visibleFor($user);

    $names = array_map(fn ($d) => $d->name, $visible);
    expect($names)->toContain('revenue.daily.total');
    expect($names)->not->toContain('stock.network.total');

    tenancy()->end();
});
