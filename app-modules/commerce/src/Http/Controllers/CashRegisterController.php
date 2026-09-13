<?php

declare(strict_types=1);

namespace Modules\Commerce\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Commerce\Http\Requests\StoreCashRegisterRequest;
use Modules\Commerce\Http\Requests\UpdateCashRegisterRequest;
use Modules\Commerce\Http\Resources\CashRegisterResource;
use Modules\Commerce\Internal\Models\CashRegister;
use Modules\Commerce\Internal\Models\PointOfSale;

final class CashRegisterController
{
    public function index(Request $request): JsonResponse
    {
        $query = CashRegister::with('pointOfSale')->where('active', true);

        if ($request->filled('point_of_sale_id')) {
            $query->where('point_of_sale_id', $request->input('point_of_sale_id'));
        }

        return response()->json(['data' => CashRegisterResource::collection($query->orderBy('name')->get())]);
    }

    public function store(StoreCashRegisterRequest $request, PointOfSale $pointOfSale): JsonResponse
    {
        $data = $request->validated();

        $register = CashRegister::create([
            'name'            => $data['name'],
            'point_of_sale_id' => $pointOfSale->id,
            'active'          => true,
        ]);

        return response()->json(['data' => new CashRegisterResource($register)], 201);
    }

    public function update(UpdateCashRegisterRequest $request, CashRegister $cashRegister): JsonResponse
    {
        $data = $request->validated();

        $cashRegister->update($data);

        return response()->json(['data' => new CashRegisterResource($cashRegister->fresh() ?? $cashRegister)]);
    }
}
