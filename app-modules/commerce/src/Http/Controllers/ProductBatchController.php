<?php

declare(strict_types=1);

namespace Modules\Commerce\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Commerce\Internal\Models\Product;
use Modules\Commerce\Internal\Models\ProductBatch;

final class ProductBatchController
{
    public function index(Product $product): JsonResponse
    {
        $batches = ProductBatch::where('product_id', $product->id)
            ->orderBy('expiry_date')
            ->get();

        return response()->json(['data' => $batches->toArray()]);
    }

    public function store(Request $request, Product $product): JsonResponse
    {
        $validated = $request->validate([
            'batch_number'       => ['required', 'string', 'max:100', 'unique:product_batches,batch_number'],
            'quantity'           => ['required', 'integer', 'min:0'],
            'expiry_date'        => ['sometimes', 'nullable', 'date'],
            'product_variant_id' => ['sometimes', 'nullable', 'uuid'],
        ]);

        $batch = ProductBatch::create([
            'product_id'         => $product->id,
            'product_variant_id' => $validated['product_variant_id'] ?? null,
            'batch_number'       => strtoupper($validated['batch_number']),
            'quantity'           => $validated['quantity'],
            'expiry_date'        => $validated['expiry_date'] ?? null,
        ]);

        return response()->json(['data' => $batch->toArray()], 201);
    }
}
