<?php

declare(strict_types=1);

namespace Modules\Commerce\Internal\Services;

use App\Platform\Identity\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

final class DashboardService
{
    /**
     * Consolidated dashboard for a user's visible POS over a date range.
     * Queries pre-computed daily_aggregates — O(POS × days), not O(sales).
     */
    public function consolidate(User $user, CarbonInterface $from, CarbonInterface $to): DashboardResult
    {
        $visiblePerimeterIds = $user->visiblePerimeters();

        if ($visiblePerimeterIds->isEmpty()) {
            return DashboardResult::empty();
        }

        $posIds = DB::table('points_of_sale')
            ->join('organizational_units', 'organizational_units.id', '=', 'points_of_sale.organizational_unit_id')
            ->whereIn('organizational_units.perimeter_id', $visiblePerimeterIds)
            ->pluck('points_of_sale.id');

        if ($posIds->isEmpty()) {
            return DashboardResult::empty();
        }

        $fromStr = $from->toDateString();
        $toStr   = $to->toDateString();

        $rows = DB::table('daily_aggregates')
            ->join('points_of_sale', 'points_of_sale.id', '=', 'daily_aggregates.point_of_sale_id')
            ->whereIn('daily_aggregates.point_of_sale_id', $posIds)
            ->where('daily_aggregates.date', '>=', $fromStr)
            ->where('daily_aggregates.date', '<=', $toStr)
            ->select([
                'daily_aggregates.point_of_sale_id as pos_id',
                'points_of_sale.name as pos_name',
                DB::raw('SUM(daily_aggregates.sale_count) as sale_count'),
                DB::raw('SUM(daily_aggregates.total_excluding_tax) as total_excluding_tax'),
                DB::raw('SUM(daily_aggregates.total_tax) as total_tax'),
                DB::raw('SUM(daily_aggregates.total_including_tax) as total_including_tax'),
                DB::raw('MAX(CASE WHEN daily_aggregates.is_provisional THEN 1 ELSE 0 END) as has_provisional'),
            ])
            ->groupBy('daily_aggregates.point_of_sale_id', 'points_of_sale.name')
            ->orderBy('points_of_sale.name')
            ->get();

        if ($rows->isEmpty()) {
            return DashboardResult::empty();
        }

        $totalSaleCount      = 0;
        $totalExcludingTax   = 0;
        $totalTax            = 0;
        $totalIncludingTax   = 0;
        $isProvisional       = false;
        $byPos               = [];

        foreach ($rows as $row) {
            $totalSaleCount    += (int) $row->sale_count;
            $totalExcludingTax += (int) $row->total_excluding_tax;
            $totalTax          += (int) $row->total_tax;
            $totalIncludingTax += (int) $row->total_including_tax;

            if ((int) $row->has_provisional === 1) {
                $isProvisional = true;
            }

            $byPos[] = [
                'pos_id'             => (string) $row->pos_id,
                'pos_name'           => (string) $row->pos_name,
                'sale_count'         => (int) $row->sale_count,
                'total_including_tax' => (int) $row->total_including_tax,
            ];
        }

        return new DashboardResult(
            totalSaleCount:    $totalSaleCount,
            totalExcludingTax: $totalExcludingTax,
            totalTax:          $totalTax,
            totalIncludingTax: $totalIncludingTax,
            isProvisional:     $isProvisional,
            byPos:             $byPos,
        );
    }
}
