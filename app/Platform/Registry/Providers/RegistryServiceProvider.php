<?php

declare(strict_types=1);

namespace App\Platform\Registry\Providers;

use App\Platform\Registry\ModuleRegistry;
use Illuminate\Support\ServiceProvider;

final class RegistryServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ModuleRegistry::class, fn () => new ModuleRegistry());
    }

    public function boot(): void
    {
        /** @var ModuleRegistry $registry */
        $registry = $this->app->make(ModuleRegistry::class);

        foreach (glob(base_path('app-modules/*/manifest.php')) ?: [] as $manifest) {
            $registry->loadFromFile($manifest);
        }
    }
}
