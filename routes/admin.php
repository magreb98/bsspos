<?php

declare(strict_types=1);

use App\Http\Controllers\Admin\AdminUserController;
use App\Http\Controllers\Admin\AuditController;
use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\Admin\DomainController;
use App\Http\Controllers\Admin\StatsController;
use App\Http\Controllers\Admin\TenantController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Admin (Landlord) API Routes
|--------------------------------------------------------------------------
|
| These routes are NOT wrapped in InitialiseTenant — they run on the central
| (landlord) database connection. Authentication uses AdminToken bearer tokens
| validated by the authenticate.admin middleware alias.
|
*/

Route::prefix('admin')->name('admin.')->group(function (): void {

    // ── Public ───────────────────────────────────────────────────────────────
    Route::post('login', [AuthController::class, 'login'])->name('login');

    // ── Protected ────────────────────────────────────────────────────────────
    Route::middleware('authenticate.admin')->group(function (): void {

        Route::post('logout', [AuthController::class, 'logout'])->name('logout');
        Route::get('me', [AuthController::class, 'me'])->name('me');

        // ── Tenants ──────────────────────────────────────────────────────────
        Route::get('tenants', [TenantController::class, 'index'])->name('tenants.index');
        Route::post('tenants', [TenantController::class, 'store'])->name('tenants.store');
        Route::get('tenants/{tenant}', [TenantController::class, 'show'])->name('tenants.show');
        Route::match(['put', 'patch'], 'tenants/{tenant}', [TenantController::class, 'update'])->name('tenants.update');
        Route::delete('tenants/{tenant}', [TenantController::class, 'destroy'])->name('tenants.destroy');
        Route::post('tenants/{tenant}/reprovision', [TenantController::class, 'reprovision'])->name('tenants.reprovision');

        // ── Domains ──────────────────────────────────────────────────────────
        Route::post('tenants/{tenant}/domains', [DomainController::class, 'store'])->name('tenants.domains.store');
        Route::delete('tenants/{tenant}/domains/{domain}', [DomainController::class, 'destroy'])->name('tenants.domains.destroy');

        // ── Platform stats ───────────────────────────────────────────────────
        Route::get('stats', [StatsController::class, 'platform'])->name('stats.platform');
        Route::get('stats/daily', [StatsController::class, 'daily'])->name('stats.daily');
        Route::get('tenants/{tenant}/metrics', [StatsController::class, 'tenantMetrics'])->name('tenants.metrics');

        // ── Superadmin users ─────────────────────────────────────────────────
        Route::get('admin-users', [AdminUserController::class, 'index'])->name('admin-users.index');
        Route::post('admin-users', [AdminUserController::class, 'store'])->name('admin-users.store');
        Route::match(['put', 'patch'], 'admin-users/{adminUser}', [AdminUserController::class, 'update'])->name('admin-users.update');
        Route::delete('admin-users/{adminUser}', [AdminUserController::class, 'destroy'])->name('admin-users.destroy');

        // ── Tenant users ─────────────────────────────────────────────────────
        Route::get('tenants/{tenant}/users', [TenantController::class, 'users'])->name('tenants.users');

        // ── MCP Audit logs ───────────────────────────────────────────────────
        Route::get('audit-logs', [AuditController::class, 'index'])->name('audit-logs.index');
        Route::get('audit-logs/export', [AuditController::class, 'export'])->name('audit-logs.export');

        // ── Tenant daily metrics ─────────────────────────────────────────────
        Route::get('tenants/{tenant}/metrics/daily', [StatsController::class, 'tenantDaily'])->name('tenants.metrics.daily');
    });
});
