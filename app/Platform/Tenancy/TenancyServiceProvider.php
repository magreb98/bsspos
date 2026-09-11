<?php

declare(strict_types=1);

namespace App\Platform\Tenancy;

use App\Platform\Tenancy\Middleware\InitialiseTenant;
use App\Platform\Tenancy\Middleware\PreventAccessFromTenant;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Stancl\Tenancy\Contracts\Tenant;
use Stancl\Tenancy\Events;
use Stancl\Tenancy\Listeners;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Stancl\Tenancy\Tenancy;

/**
 * Enregistre l'infrastructure multi-tenant :
 *   - listeners Stancl (BootstrapTenancy, RevertToCentralContext),
 *   - macro `tenant()` sur Tenancy pour exposition via la façade,
 *   - groupe de middleware `tenant`,
 *   - middleware nommé `prevent.tenant.access`,
 *   - routes tenant depuis `routes/tenant.php`.
 */
final class TenancyServiceProvider extends ServiceProvider
{
    /**
     * Enregistre les bindings nécessaires au multi-tenant.
     */
    public function register(): void
    {
        // Les bootstrappers sont enregistrés comme singletons par le TenancyServiceProvider de Stancl.
        // Aucune surcharge nécessaire ici.
    }

    /**
     * Démarre l'infrastructure de routage tenant.
     */
    public function boot(): void
    {
        // ── 1. Listeners Stancl ─────────────────────────────────────────────
        // Sans ces listeners, les bootstrappers ne sont jamais appelés.
        Event::listen(Events\TenancyInitialized::class, Listeners\BootstrapTenancy::class);
        Event::listen(Events\TenancyEnded::class, Listeners\RevertToCentralContext::class);

        // ── 2. Macro `tenant()` ─────────────────────────────────────────────
        // Expose la propriété `$tenant` de Tenancy comme méthode `tenant()`
        // pour satisfaire \Stancl\Tenancy\Facades\Tenancy::tenant() dans les tests.
        Tenancy::macro('tenant', function (): ?Tenant {
            /** @var Tenancy $this */
            $t = $this->tenant;

            return $t instanceof Tenant ? $t : null;
        });

        // ── 3. Groupe et alias de middleware ─────────────────────────────────
        /** @var Router $router */
        $router = $this->app->make(Router::class);

        // Groupe de middleware appliqué aux routes tenant uniquement.
        $router->middlewareGroup('tenant', [
            'web',
            InitialiseTenant::class,
        ]);

        // Middleware nommé pour protéger les routes landlord contre l'accès tenant.
        $router->aliasMiddleware('prevent.tenant.access', PreventAccessFromTenant::class);

        // ── 4. Priorité du middleware d'identification ────────────────────────
        $this->app->make(\Illuminate\Contracts\Http\Kernel::class)
            ->prependToMiddlewarePriority(InitializeTenancyByDomain::class);

        // ── 5. Routes tenant ─────────────────────────────────────────────────
        $this->loadTenantRoutes();
    }

    /**
     * Charge les routes déclarées dans routes/tenant.php.
     *
     * Les middlewares sont appliqués directement dans routes/tenant.php.
     */
    private function loadTenantRoutes(): void
    {
        if (! $this->app->routesAreCached() && file_exists(base_path('routes/tenant.php'))) {
            Route::group([], base_path('routes/tenant.php'));
        }
    }
}
