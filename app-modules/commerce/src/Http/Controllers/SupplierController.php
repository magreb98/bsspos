<?php

declare(strict_types=1);

namespace Modules\Commerce\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Modules\Commerce\Http\Requests\StoreSupplierRequest;
use Modules\Commerce\Http\Requests\UpdateSupplierRequest;
use Modules\Commerce\Http\Resources\SupplierResource;
use Modules\Commerce\Internal\Models\Supplier;

final class SupplierController
{
    public function index(): JsonResponse
    {
        return response()->json(['data' => SupplierResource::collection(Supplier::orderBy('name')->get())]);
    }

    public function store(StoreSupplierRequest $request): JsonResponse
    {
        $data = $request->validated();

        $supplier = Supplier::create(['name' => $data['name'], 'active' => true]);

        return response()->json(['data' => new SupplierResource($supplier)], 201);
    }

    public function show(Supplier $supplier): JsonResponse
    {
        return response()->json(['data' => new SupplierResource($supplier->load('orders'))]);
    }

    public function update(UpdateSupplierRequest $request, Supplier $supplier): JsonResponse
    {
        $data = $request->validated();

        $supplier->update($data);

        return response()->json(['data' => new SupplierResource($supplier->fresh() ?? $supplier)]);
    }
}
