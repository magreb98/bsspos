<?php

declare(strict_types=1);

namespace Modules\Commerce\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Commerce\Internal\Enums\SupplierOrderStatus;
use Modules\Commerce\Internal\Models\SupplierOrder;

final class SupplierOrderController
{
    public function index(Request $request): JsonResponse
    {
        $query = SupplierOrder::with('supplier')->latest();

        if ($request->filled('supplier_id')) {
            $query->where('supplier_id', $request->input('supplier_id'));
        }

        return response()->json(['data' => $query->get()]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'supplier_id'        => 'required|uuid',
            'lines'              => 'required|array|min:1',
            'lines.*.product_id' => 'required|uuid',
            'lines.*.quantity'   => 'required|integer|min:1',
            'lines.*.unit_cost'  => 'required|integer|min:0',
        ]);

        $order = SupplierOrder::create([
            'supplier_id' => $data['supplier_id'],
            'status'      => SupplierOrderStatus::Draft,
            'ordered_at'  => now(),
        ]);

        foreach ($data['lines'] as $line) {
            $order->lines()->create([
                'product_id' => $line['product_id'],
                'quantity'   => $line['quantity'],
                'unit_cost'  => $line['unit_cost'],
            ]);
        }

        return response()->json(['data' => $order->fresh()?->load('lines', 'supplier')], 201);
    }

    public function show(SupplierOrder $supplierOrder): JsonResponse
    {
        return response()->json(['data' => $supplierOrder->load('supplier', 'lines.product', 'receptions')]);
    }
}
