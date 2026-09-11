<?php

declare(strict_types=1);

namespace App\Providers;

use App\Platform\Mcp\MetricAuthorizerContract;
use App\Platform\Mcp\MetricListingContract;
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
    }
}
