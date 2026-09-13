<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Control\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

final class HealthController
{
    public function index(): JsonResponse
    {
        $checks = [];

        // Central DB
        try {
            DB::select('SELECT 1');
            $checks['central_database'] = [
                'status'  => 'ok',
                'message' => 'Base centrale opérationnelle.',
            ];
        } catch (Throwable $e) {
            $checks['central_database'] = [
                'status'  => 'error',
                'message' => $e->getMessage(),
            ];
        }

        // Cache driver
        try {
            $key = 'health_check_' . microtime(true);
            Cache::put($key, true, 5);
            Cache::get($key);
            Cache::forget($key);
            $checks['cache'] = [
                'status'  => 'ok',
                'message' => 'Cache opérationnel.',
            ];
        } catch (Throwable $e) {
            $checks['cache'] = [
                'status'  => 'error',
                'message' => $e->getMessage(),
            ];
        }

        // Queue — count failed jobs
        try {
            $failed = DB::table('failed_jobs')->count();
            $checks['queue'] = [
                'status'      => $failed > 10 ? 'warning' : 'ok',
                'message'     => $failed > 0 ? "{$failed} job(s) échoué(s)." : 'Aucun job échoué.',
                'failed_jobs' => $failed,
            ];
        } catch (Throwable) {
            $checks['queue'] = [
                'status'  => 'warning',
                'message' => 'Table failed_jobs inaccessible.',
            ];
        }

        // Sample tenant DB
        $sampleTenant = Tenant::where('status', 'actif')->inRandomOrder()->first();
        if ($sampleTenant !== null) {
            try {
                $sampleTenant->run(static fn () => DB::select('SELECT 1'));
                $checks['tenant_database'] = [
                    'status'  => 'ok',
                    'message' => 'Base tenant (échantillon) opérationnelle.',
                ];
            } catch (Throwable $e) {
                $checks['tenant_database'] = [
                    'status'  => 'error',
                    'message' => $e->getMessage(),
                ];
            }
        } else {
            $checks['tenant_database'] = [
                'status'  => 'ok',
                'message' => 'Aucun tenant actif à vérifier.',
            ];
        }

        // Provisioning backlog
        $inProvisioning = Tenant::where('status', 'provisionning')->count();
        $checks['provisioning'] = [
            'status'  => $inProvisioning > 0 ? 'info' : 'ok',
            'message' => $inProvisioning > 0
                ? "{$inProvisioning} tenant(s) en cours de provisionnement."
                : 'Aucun provisionnement en cours.',
            'count'   => $inProvisioning,
        ];

        $overall = collect($checks)->pluck('status')->contains('error') ? 'degraded' : 'healthy';

        return response()->json([
            'data' => [
                'status'     => $overall,
                'checks'     => $checks,
                'checked_at' => now()->toIso8601String(),
            ],
        ]);
    }
}
