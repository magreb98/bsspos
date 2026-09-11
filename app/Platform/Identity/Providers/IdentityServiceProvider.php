<?php

declare(strict_types=1);

namespace App\Platform\Identity\Providers;

use App\Platform\Identity\Models\User;
use Illuminate\Support\ServiceProvider;

final class IdentityServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        config(['permission.models.model' => User::class]);
    }

    public function boot(): void
    {
        // Nothing to do here: PrefixPermissionsCache is declared
        // directly in config/tenancy.php to guarantee bootstrap order.
    }
}
