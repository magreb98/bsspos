<?php

declare(strict_types=1);

use App\Platform\Mcp\McpController;
use App\Platform\Mcp\Middleware\AuthenticateMcpRequest;
use App\Platform\Tenancy\Middleware\InitialiseTenant;
use App\Platform\Tenancy\Middleware\PreventAccessFromTenant;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Routes tenant
|--------------------------------------------------------------------------
|
| Ces routes sont dans le groupe de middleware `web` + `InitialiseTenant`.
| InitialiseTenant résout le tenant courant depuis le domaine HTTP.
| Les routes protégées par `prevent.tenant.access` renvoient 403
| si elles sont appelées depuis un contexte tenant initialisé.
|
*/

Route::middleware(['web', InitialiseTenant::class])->group(function (): void {
    Route::get('/', static function (): string {
        return 'Tenant : ' . tenant('id');
    });

    // Route du plan de contrôle — protégée contre l'accès depuis un contexte tenant.
    Route::get('/control', static function (): string {
        return 'Plan de contrôle';
    })->middleware(PreventAccessFromTenant::class);
});

Route::middleware(['web', InitialiseTenant::class, AuthenticateMcpRequest::class])
    ->prefix('mcp')
    ->group(function (): void {
        Route::get('tools', [McpController::class, 'tools']);
        Route::post('tools/call', [McpController::class, 'call']);
    });

// InitialiseTenant MUST run before SubstituteBindings so route model binding
// resolves models from the tenant database (not the central one).
Route::middleware([
    \Illuminate\Foundation\Http\Middleware\ValidatePostSize::class,
    \Illuminate\Foundation\Http\Middleware\ConvertEmptyStringsToNull::class,
    \Illuminate\Http\Middleware\HandleCors::class,
    InitialiseTenant::class,
    \Illuminate\Routing\Middleware\SubstituteBindings::class,
])->group(function (): void {
    require base_path('app-modules/commerce/routes/api.php');
    require base_path('app-modules/secteur-electronique/routes/api.php');
});
