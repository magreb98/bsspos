<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Control\Tenant;
use Carbon\CarbonPeriod;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Throwable;

final class StatsController
{
    public function platform(Request $request): JsonResponse
    {
        $from = $request->input('from', now()->subDays(30)->toDateString());
        $to   = $request->input('to', now()->toDateString());

        $totalTenants = Tenant::count();
        $active       = Tenant::where('status', 'actif')->count();
        $suspended    = Tenant::where('status', 'suspendu')->count();
        $provisioning = Tenant::where('status', 'provisionning')->count();
        $newThisMonth = Tenant::where('created_at', '>=', now()->startOfMonth()->toDateString())->count();

        [$totalRevenue, $totalSales] = $this->aggregateRevenue($from, $to);

        return response()->json([
            'data' => [
                'total_tenants'           => $totalTenants,
                'active_tenants'          => $active,
                'suspended_tenants'       => $suspended,
                'tenants_in_provisioning' => $provisioning,
                'new_this_month'          => $newThisMonth,
                'total_revenue_xaf'       => $totalRevenue,
                'total_sales'             => $totalSales,
                'period'                  => ['from' => $from, 'to' => $to],
            ],
        ]);
    }

    public function daily(Request $request): JsonResponse
    {
        $from = $request->input('from', now()->subDays(30)->toDateString());
        $to   = $request->input('to', now()->toDateString());

        // Pre-fill every date in the range with zeros
        $map    = [];
        $period = CarbonPeriod::create($from, $to);
        foreach ($period as $day) {
            $map[$day->toDateString()] = [
                'date'                => $day->toDateString(),
                'total_including_tax' => 0,
                'sale_count'          => 0,
            ];
        }

        foreach (Tenant::where('status', 'actif')->cursor() as $tenant) {
            try {
                $tenant->run(function () use (&$map, $from, $to): void {
                    $rows = DB::table('daily_aggregates')
                        ->whereBetween('date', [$from, $to])
                        ->get(['date', 'total_including_tax', 'sale_count']);

                    foreach ($rows as $row) {
                        $date = (string) $row->date;
                        if (isset($map[$date])) {
                            $map[$date]['total_including_tax'] += (int) $row->total_including_tax;
                            $map[$date]['sale_count']          += (int) $row->sale_count;
                        }
                    }
                });
            } catch (Throwable) {
                // Tenant DB not ready or missing table — skip silently
            }
        }

        return response()->json(['data' => array_values($map)]);
    }

    public function tenantMetrics(Request $request, string $tenantId): JsonResponse
    {
        $tenant = Tenant::find($tenantId);

        if (! $tenant instanceof Tenant) {
            return response()->json(['code' => 'NOT_FOUND', 'message' => 'Entreprise introuvable.'], 404);
        }

        $from = $request->input('from', now()->subDays(30)->toDateString());
        $to   = $request->input('to', now()->toDateString());

        $totalRevenue = 0;
        $totalSales   = 0;
        $lastActivity = null;
        $userCount    = 0;

        try {
            $tenant->run(function () use (&$totalRevenue, &$totalSales, &$lastActivity, &$userCount, $from, $to): void {
                $totalRevenue = (int) DB::table('daily_aggregates')
                    ->whereBetween('date', [$from, $to])
                    ->sum('total_including_tax');

                $totalSales = (int) DB::table('daily_aggregates')
                    ->whereBetween('date', [$from, $to])
                    ->sum('sale_count');

                $lastActivity = DB::table('daily_aggregates')
                    ->where('sale_count', '>', 0)
                    ->orderByDesc('date')
                    ->value('date');

                $userCount = (int) DB::table('members')->where('active', true)->count();
            });
        } catch (Throwable) {
            // Tenant DB not ready
        }

        return response()->json([
            'data' => [
                'tenant_id'           => $tenantId,
                'total_including_tax' => $totalRevenue,
                'sale_count'          => $totalSales,
                'average_basket'      => $totalSales > 0 ? intdiv($totalRevenue, $totalSales) : 0,
                'active_users'        => $userCount,
                'last_activity_date'  => $lastActivity,
                'period'              => ['from' => $from, 'to' => $to],
            ],
        ]);
    }

    public function tenantDaily(Request $request, string $tenantId): JsonResponse
    {
        $tenant = Tenant::find($tenantId);

        if (! $tenant instanceof Tenant) {
            return response()->json(['code' => 'NOT_FOUND', 'message' => 'Entreprise introuvable.'], 404);
        }

        $from = $request->input('from', now()->subDays(30)->toDateString());
        $to   = $request->input('to', now()->toDateString());

        $map    = [];
        $period = CarbonPeriod::create($from, $to);
        foreach ($period as $day) {
            $map[$day->toDateString()] = [
                'date'                => $day->toDateString(),
                'total_including_tax' => 0,
                'sale_count'          => 0,
            ];
        }

        try {
            $tenant->run(function () use (&$map, $from, $to): void {
                $rows = DB::table('daily_aggregates')
                    ->whereBetween('date', [$from, $to])
                    ->get(['date', 'total_including_tax', 'sale_count']);

                foreach ($rows as $row) {
                    $date = (string) $row->date;
                    if (isset($map[$date])) {
                        $map[$date]['total_including_tax'] = (int) $row->total_including_tax;
                        $map[$date]['sale_count']          = (int) $row->sale_count;
                    }
                }
            });
        } catch (Throwable) {
            // Tenant DB not ready
        }

        return response()->json(['data' => array_values($map)]);
    }

    /** @return array{int, int} */
    private function aggregateRevenue(string $from, string $to): array
    {
        $totalRevenue = 0;
        $totalSales   = 0;

        foreach (Tenant::where('status', 'actif')->cursor() as $tenant) {
            try {
                $tenant->run(function () use (&$totalRevenue, &$totalSales, $from, $to): void {
                    $totalRevenue += (int) DB::table('daily_aggregates')
                        ->whereBetween('date', [$from, $to])
                        ->sum('total_including_tax');

                    $totalSales += (int) DB::table('daily_aggregates')
                        ->whereBetween('date', [$from, $to])
                        ->sum('sale_count');
                });
            } catch (Throwable) {
                // Tenant DB not ready or missing table — skip silently
            }
        }

        return [$totalRevenue, $totalSales];
    }
}
