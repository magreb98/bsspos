<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Control\Tenant;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Throwable;

final class AnalyticsController
{
    /**
     * Monthly tenant growth for the last N months.
     */
    public function growth(Request $request): JsonResponse
    {
        $months = min((int) $request->input('months', 12), 24);

        $series = [];
        for ($i = $months - 1; $i >= 0; $i--) {
            $start = now()->subMonths($i)->startOfMonth();
            $end   = now()->subMonths($i)->endOfMonth();

            $series[] = [
                'month'          => $start->format('Y-m'),
                'label'          => $start->locale('fr')->isoFormat('MMM YYYY'),
                'new_tenants'    => Tenant::whereBetween('created_at', [$start, $end])->count(),
                'active_tenants' => Tenant::where('status', 'actif')
                    ->where('created_at', '<=', $end)
                    ->count(),
            ];
        }

        $currentActive = Tenant::where('status', 'actif')->count();
        $previousActive = Tenant::where('status', 'actif')
            ->where('created_at', '<=', now()->subMonth()->endOfMonth())
            ->count();

        $growthRate = $previousActive > 0
            ? round(($currentActive - $previousActive) / $previousActive * 100, 1)
            : 0.0;

        return response()->json([
            'data' => [
                'series'  => $series,
                'summary' => [
                    'total_active'     => $currentActive,
                    'total_suspended'  => Tenant::where('status', 'suspendu')->count(),
                    'growth_rate_pct'  => $growthRate,
                    'new_this_month'   => Tenant::where('created_at', '>=', now()->startOfMonth())->count(),
                ],
            ],
        ]);
    }

    /**
     * Monthly revenue + sales trends with current vs previous month comparison.
     */
    public function revenue(Request $request): JsonResponse
    {
        $months = min((int) $request->input('months', 12), 24);

        $series = [];
        for ($i = $months - 1; $i >= 0; $i--) {
            $start = now()->subMonths($i)->startOfMonth()->toDateString();
            $end   = now()->subMonths($i)->endOfMonth()->toDateString();

            [$rev, $sales] = $this->aggregateForPeriod($start, $end);

            $series[] = [
                'month'   => Carbon::parse($start)->format('Y-m'),
                'label'   => Carbon::parse($start)->locale('fr')->isoFormat('MMM YYYY'),
                'revenue' => $rev,
                'sales'   => $sales,
            ];
        }

        $currentFrom = now()->startOfMonth()->toDateString();
        $currentTo   = now()->toDateString();
        $prevFrom    = now()->subMonth()->startOfMonth()->toDateString();
        $prevTo      = now()->subMonth()->endOfMonth()->toDateString();

        [$curRev, $curSales]   = $this->aggregateForPeriod($currentFrom, $currentTo);
        [$prevRev, $prevSales] = $this->aggregateForPeriod($prevFrom, $prevTo);

        return response()->json([
            'data' => [
                'series'     => $series,
                'comparison' => [
                    'current'           => ['from' => $currentFrom, 'to' => $currentTo, 'revenue' => $curRev,  'sales' => $curSales],
                    'previous'          => ['from' => $prevFrom,    'to' => $prevTo,    'revenue' => $prevRev, 'sales' => $prevSales],
                    'revenue_growth_pct' => $prevRev > 0 ? round(($curRev - $prevRev) / $prevRev * 100, 1) : 0.0,
                    'sales_growth_pct'  => $prevSales > 0 ? round(($curSales - $prevSales) / $prevSales * 100, 1) : 0.0,
                ],
            ],
        ]);
    }

    /** @return array{int, int} */
    private function aggregateForPeriod(string $from, string $to): array
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
                // Tenant DB not ready or table missing — skip silently
            }
        }

        return [$totalRevenue, $totalSales];
    }
}
