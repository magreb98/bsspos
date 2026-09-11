<?php

declare(strict_types=1);

namespace Modules\Commerce\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Commerce\Internal\Models\PointOfSale;
use Modules\Commerce\Internal\Models\SupplierOrder;
use Modules\Commerce\Internal\Services\ReceptionService;

final class ReceptionController
{
    public function __construct(private readonly ReceptionService $service)
    {
    }

    public function store(Request $request, SupplierOrder $supplierOrder): JsonResponse
    {
        $data = $request->validate([
            'point_of_sale_id'              => 'required|uuid',
            'lines'                         => 'required|array|min:1',
            'lines.*.product_id'            => 'required|uuid',
            'lines.*.quantity_expected'     => 'required|integer|min:0',
            'lines.*.quantity_received'     => 'required|integer|min:0',
            'lines.*.unit_cost'             => 'required|integer|min:0',
        ]);

        $pos = PointOfSale::where('id', $data['point_of_sale_id'])->firstOrFail();

        /** @var list<array{product_id: string, quantity_expected: int, quantity_received: int, unit_cost: int}> $lines */
        $lines = array_values($data['lines']);

        $reception = $this->service->receive($supplierOrder, $pos, $lines);

        return response()->json(['data' => $reception->load('lines.product')], 201);
    }

    public function show(SupplierOrder $supplierOrder): JsonResponse
    {
        return response()->json(['data' => $supplierOrder->receptions()->with('lines.product')->get()]);
    }
}
