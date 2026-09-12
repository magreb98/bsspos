<?php

declare(strict_types=1);

namespace Modules\Commerce\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Modules\Commerce\Http\Requests\CustomerRankingReportRequest;
use Modules\Commerce\Http\Requests\MarginReportRequest;
use Modules\Commerce\Http\Requests\StockRotationReportRequest;
use Modules\Commerce\Http\Requests\TopProductsReportRequest;
use Modules\Commerce\Internal\Enums\SaleState;

final class ReportController
{
    public function topProducts(TopProductsReportRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $limit = $validated['limit'] ?? 10;

        $query = DB::table('sale_lines')
            ->join('sales', 'sales.id', '=', 'sale_lines.sale_id')
            ->join('products', 'products.id', '=', 'sale_lines.product_id')
            ->where('sales.state', SaleState::Confirmed->value)
            ->whereNull('sales.return_of_sale_id')
            ->groupBy('products.id', 'products.label', 'products.reference')
            ->select([
                'products.id',
                'products.label',
                'products.reference',
                DB::raw('SUM(sale_lines.quantity) as total_quantity'),
                DB::raw('SUM(sale_lines.line_total_including_tax) as total_revenue'),
            ])
            ->orderByDesc('total_revenue')
            ->limit($limit);

        if (isset($validated['from'])) {
            $query->whereDate('sales.confirmed_at', '>=', $validated['from']);
        }

        if (isset($validated['to'])) {
            $query->whereDate('sales.confirmed_at', '<=', $validated['to']);
        }

        return response()->json(['data' => $query->get()->toArray()]);
    }

    public function margin(MarginReportRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $query = DB::table('sale_lines')
            ->join('sales', 'sales.id', '=', 'sale_lines.sale_id')
            ->join('products', 'products.id', '=', 'sale_lines.product_id')
            ->where('sales.state', SaleState::Confirmed->value)
            ->whereNull('sales.return_of_sale_id')
            ->select([
                DB::raw('SUM(sale_lines.line_total_excluding_tax) as total_ht'),
                DB::raw('SUM(sale_lines.line_total_tax) as total_tax'),
                DB::raw('SUM(sale_lines.line_total_including_tax) as total_ttc'),
                DB::raw('COUNT(DISTINCT sales.id) as sale_count'),
            ]);

        if (isset($validated['from'])) {
            $query->whereDate('sales.confirmed_at', '>=', $validated['from']);
        }

        if (isset($validated['to'])) {
            $query->whereDate('sales.confirmed_at', '<=', $validated['to']);
        }

        if (isset($validated['family_id'])) {
            $query->where('products.family_id', $validated['family_id']);
        }

        return response()->json(['data' => $query->first()]);
    }

    public function customerRanking(CustomerRankingReportRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $limit = $validated['limit'] ?? 10;

        $query = DB::table('sales')
            ->join('customers', 'customers.id', '=', 'sales.client_id')
            ->where('sales.state', SaleState::Confirmed->value)
            ->whereNull('sales.return_of_sale_id')
            ->whereNotNull('sales.client_id')
            ->groupBy('customers.id', 'customers.name', 'customers.phone')
            ->select([
                'customers.id',
                'customers.name',
                'customers.phone',
                DB::raw('COUNT(sales.id) as visit_count'),
                DB::raw('SUM(sales.total_including_tax) as total_spent'),
            ])
            ->orderByDesc('total_spent')
            ->limit($limit);

        if (isset($validated['from'])) {
            $query->whereDate('sales.confirmed_at', '>=', $validated['from']);
        }

        if (isset($validated['to'])) {
            $query->whereDate('sales.confirmed_at', '<=', $validated['to']);
        }

        return response()->json(['data' => $query->get()->toArray()]);
    }

    public function stockRotation(StockRotationReportRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $query = DB::table('stock_movements')
            ->join('products', 'products.id', '=', 'stock_movements.product_id')
            ->where('stock_movements.quantity', '<', 0)
            ->groupBy('products.id', 'products.label', 'products.reference', 'stock_movements.point_of_sale_id')
            ->select([
                'products.id',
                'products.label',
                'products.reference',
                'stock_movements.point_of_sale_id',
                DB::raw('ABS(SUM(stock_movements.quantity)) as units_sold'),
            ])
            ->orderByDesc('units_sold');

        if (isset($validated['from'])) {
            $query->whereDate('stock_movements.occurred_at', '>=', $validated['from']);
        }

        if (isset($validated['to'])) {
            $query->whereDate('stock_movements.occurred_at', '<=', $validated['to']);
        }

        if (isset($validated['point_of_sale_id'])) {
            $query->where('stock_movements.point_of_sale_id', $validated['point_of_sale_id']);
        }

        return response()->json(['data' => $query->get()->toArray()]);
    }
}
