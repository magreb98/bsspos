<?php

declare(strict_types=1);

namespace App\Providers;

use App\Platform\Mcp\MetricAuthorizerContract;
use App\Platform\Mcp\MetricListingContract;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Modules\Commerce\Internal\Metrics\MetricAuthorizer;
use Modules\Commerce\Internal\Metrics\MetricCatalog;
use Modules\Commerce\Internal\Metrics\MetricDefinition;
use Modules\Commerce\Internal\Metrics\MetricGuard;
use Modules\Commerce\Internal\Metrics\MetricListing;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(MetricCatalog::class);
        $this->app->singleton(MetricGuard::class);
        $this->app->singleton(MetricAuthorizerContract::class, MetricAuthorizer::class);
        $this->app->singleton(MetricListingContract::class, MetricListing::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $catalog = $this->app->make(MetricCatalog::class);

        $catalog->register(new MetricDefinition(
            name: 'ca_ttc',
            permission: 'commerce.rapport.lire',
            label: "Chiffre d'affaires TTC",
        ));

        $catalog->register(new MetricDefinition(
            name: 'nombre_ventes',
            permission: 'commerce.rapport.lire',
            label: 'Nombre de ventes',
        ));

        // Catch N+1 / lazy-loading regressions in dev & CI instead of only
        // discovering them as slow endpoints in production.
        Model::preventLazyLoading(! $this->app->isProduction());
        Model::preventSilentlyDiscardingAttributes(! $this->app->isProduction());

        $this->configureRateLimiting();
    }

    private function configureRateLimiting(): void
    {
        // Admin (landlord) login — keyed by email + IP so one leaked/guessed
        // email can't be locked out by an unrelated attacker sharing an IP,
        // and one IP can't grind through many admin emails unthrottled either.
        RateLimiter::for('admin-login', function (Request $request) {
            $key = mb_strtolower((string) $request->input('email')) . '|' . $request->ip();

            return [
                Limit::perMinute(5)->by($key),
                Limit::perMinute(20)->by($request->ip()),
            ];
        });

        // Member (tenant) login — same shape, keyed by phone since that's
        // the member login identifier instead of email.
        RateLimiter::for('member-login', function (Request $request) {
            $key = (string) $request->input('phone') . '|' . $request->ip();

            return [
                Limit::perMinute(5)->by($key),
                Limit::perMinute(20)->by($request->ip()),
            ];
        });

        // General fallback for every other API surface (commerce, electronics,
        // mcp) that has no more specific limiter of its own.
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(120)->by($request->user()?->getAuthIdentifier() ?? $request->ip());
        });
    }
}
