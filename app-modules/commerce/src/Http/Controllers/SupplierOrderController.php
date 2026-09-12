<?php

declare(strict_types=1);

namespace Modules\Commerce\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Commerce\Http\Requests\StoreSupplierOrderRequest;
use Modules\Commerce\Http\Resources\SupplierOrderResource;
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

        return response()->json(['data' => SupplierOrderResource::collection($query->get())]);
    }

    public function store(StoreSupplierOrderRequest $request): JsonResponse
    {
        $data = $request->validated();

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

        $freshOrder = $order->fresh()?->load('lines', 'supplier');

        return response()->json(['data' => $freshOrder !== null ? new SupplierOrderResource($freshOrder) : null], 201);
    }

    public function show(SupplierOrder $supplierOrder): JsonResponse
    {
        return response()->json(['data' => new SupplierOrderResource($supplierOrder->load('supplier', 'lines.product', 'receptions'))]);
    }
}
