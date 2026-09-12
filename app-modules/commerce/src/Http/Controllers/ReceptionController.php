<?php

declare(strict_types=1);

namespace Modules\Commerce\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Modules\Commerce\Http\Requests\StoreReceptionRequest;
use Modules\Commerce\Http\Resources\ReceptionResource;
use Modules\Commerce\Internal\Models\PointOfSale;
use Modules\Commerce\Internal\Models\SupplierOrder;
use Modules\Commerce\Internal\Services\ReceptionService;

final class ReceptionController
{
    public function __construct(private readonly ReceptionService $service)
    {
    }

    public function store(StoreReceptionRequest $request, SupplierOrder $supplierOrder): JsonResponse
    {
        $data = $request->validated();

        $pos = PointOfSale::where('id', $data['point_of_sale_id'])->firstOrFail();

        /** @var list<array{product_id: string, quantity_expected: int, quantity_received: int, unit_cost: int}> $lines */
        $lines = array_values($data['lines']);

        $reception = $this->service->receive($supplierOrder, $pos, $lines);

        return response()->json(['data' => new ReceptionResource($reception->load('lines.product'))], 201);
    }

    public function show(SupplierOrder $supplierOrder): JsonResponse
    {
        return response()->json(['data' => ReceptionResource::collection($supplierOrder->receptions()->with('lines.product')->get())]);
    }
}
