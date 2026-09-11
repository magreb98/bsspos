<?php

declare(strict_types=1);

namespace App\Platform\Audit\Providers;

use App\Platform\Identity\Models\User;
use Illuminate\Support\Facades\App;
use Illuminate\Support\ServiceProvider;

final class AuditServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        config([
            'audit.driver'      => 'database',
            'audit.user.model'  => User::class,
            'audit.user.guards' => ['web'],
            // D2: enable auditing in test runs (tests execute in CLI context where
            // App::runningInConsole() is true, which would suppress auditing by default).
            'audit.console'     => App::runningUnitTests(),
        ]);
    }

    public function boot(): void
    {
        // D3: Spatie models (Role, Permission) do not implement the Auditable trait —
        // they are therefore never audited.
    }
}
