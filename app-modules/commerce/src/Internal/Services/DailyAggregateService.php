<?php

declare(strict_types=1);

namespace Modules\Commerce\Internal\Services;

use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Modules\Commerce\Internal\Enums\SaleState;
use Modules\Commerce\Internal\Enums\SessionState;
use Modules\Commerce\Internal\Models\DailyAggregate;
use Modules\Commerce\Internal\Models\PointOfSale;

final class DailyAggregateService
{
    /**
     * (Re)computes the daily aggregate for a given POS and date.
     * Safe to call multiple times — idempotent.
     *
     * Chain: sales → cash_sessions → cash_registers → points_of_sale
     */
    public function recompute(PointOfSale $pos, CarbonInterface $date): DailyAggregate
    {
        $dateStr = $date->toDateString();

        $totals = DB::table('sales')
            ->join('cash_sessions', 'cash_sessions.id', '=', 'sales.cash_session_id')
            ->join('cash_registers', 'cash_registers.id', '=', 'cash_sessions.cash_register_id')
            ->where('cash_registers.point_of_sale_id', $pos->id)
            ->where('sales.state', SaleState::Confirmed->value)
            ->whereDate('sales.confirmed_at', $dateStr)
            ->selectRaw('
                COUNT(*) as sale_count,
                COALESCE(SUM(sales.total_excluding_tax), 0) as total_excluding_tax,
                COALESCE(SUM(sales.total_tax), 0) as total_tax,
                COALESCE(SUM(sales.total_including_tax), 0) as total_including_tax
            ')
            ->first();

        $isProvisional = DB::table('cash_sessions')
            ->join('cash_registers', 'cash_registers.id', '=', 'cash_sessions.cash_register_id')
            ->where('cash_registers.point_of_sale_id', $pos->id)
            ->where('cash_sessions.state', SessionState::Open->value)
            ->whereDate('cash_sessions.opened_at', $dateStr)
            ->exists();

        $aggregate = DailyAggregate::where('point_of_sale_id', $pos->id)
            ->where('date', $dateStr)
            ->first()
            ?? new DailyAggregate(['point_of_sale_id' => $pos->id, 'date' => $dateStr]);

        $aggregate->fill([
            'sale_count'           => $totals !== null ? (int) $totals->sale_count : 0,
            'total_excluding_tax'  => $totals !== null ? (int) $totals->total_excluding_tax : 0,
            'total_tax'            => $totals !== null ? (int) $totals->total_tax : 0,
            'total_including_tax'  => $totals !== null ? (int) $totals->total_including_tax : 0,
            'is_provisional'       => $isProvisional,
            'computed_at'          => now(),
        ]);

        $aggregate->save();

        return $aggregate;
    }
}
