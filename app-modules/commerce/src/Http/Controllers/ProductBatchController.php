<?php

declare(strict_types=1);

namespace Modules\Commerce\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Modules\Commerce\Http\Requests\StoreProductBatchRequest;
use Modules\Commerce\Http\Resources\ProductBatchResource;
use Modules\Commerce\Internal\Models\Product;
use Modules\Commerce\Internal\Models\ProductBatch;

final class ProductBatchController
{
    public function index(Product $product): JsonResponse
    {
        $batches = ProductBatch::where('product_id', $product->id)
            ->orderBy('expiry_date')
            ->get();

        return response()->json(['data' => ProductBatchResource::collection($batches)]);
    }

    public function store(StoreProductBatchRequest $request, Product $product): JsonResponse
    {
        $validated = $request->validated();

        $batch = ProductBatch::create([
            'product_id'         => $product->id,
            'product_variant_id' => $validated['product_variant_id'] ?? null,
            'batch_number'       => strtoupper($validated['batch_number']),
            'quantity'           => $validated['quantity'],
            'expiry_date'        => $validated['expiry_date'] ?? null,
        ]);

        return response()->json(['data' => new ProductBatchResource($batch)], 201);
    }
}
