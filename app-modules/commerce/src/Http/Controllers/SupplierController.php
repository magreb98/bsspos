<?php

declare(strict_types=1);

namespace Modules\Commerce\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Commerce\Internal\Models\Supplier;

final class SupplierController
{
    public function index(): JsonResponse
    {
        return response()->json(['data' => Supplier::orderBy('name')->get()]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate(['name' => 'required|string|max:255']);

        $supplier = Supplier::create(['name' => $data['name'], 'active' => true]);

        return response()->json(['data' => $supplier], 201);
    }

    public function show(Supplier $supplier): JsonResponse
    {
        return response()->json(['data' => $supplier->load('orders')]);
    }

    public function update(Request $request, Supplier $supplier): JsonResponse
    {
        $data = $request->validate([
            'name'   => 'sometimes|string|max:255',
            'active' => 'sometimes|boolean',
        ]);

        $supplier->update($data);

        return response()->json(['data' => $supplier->fresh()]);
    }
}
