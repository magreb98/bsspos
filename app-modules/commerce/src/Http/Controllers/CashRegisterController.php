<?php

declare(strict_types=1);

namespace Modules\Commerce\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Commerce\Internal\Models\CashRegister;
use Modules\Commerce\Internal\Models\PointOfSale;

final class CashRegisterController
{
    public function index(Request $request): JsonResponse
    {
        $query = CashRegister::with('pointOfSale');

        if ($request->filled('point_of_sale_id')) {
            $query->where('point_of_sale_id', $request->input('point_of_sale_id'));
        }

        return response()->json(['data' => $query->orderBy('name')->get()]);
    }

    public function store(Request $request, PointOfSale $pointOfSale): JsonResponse
    {
        $data = $request->validate(['name' => 'required|string|max:255']);

        $register = CashRegister::create([
            'name'            => $data['name'],
            'point_of_sale_id' => $pointOfSale->id,
            'active'          => true,
        ]);

        return response()->json(['data' => $register], 201);
    }

    public function update(Request $request, CashRegister $cashRegister): JsonResponse
    {
        $data = $request->validate([
            'name'   => 'sometimes|string|max:255',
            'active' => 'sometimes|boolean',
        ]);

        $cashRegister->update($data);

        return response()->json(['data' => $cashRegister->fresh()]);
    }
}
