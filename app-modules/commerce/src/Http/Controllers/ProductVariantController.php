<?php

declare(strict_types=1);

namespace Modules\Commerce\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Modules\Commerce\Http\Requests\StoreProductVariantRequest;
use Modules\Commerce\Http\Requests\UpdateProductVariantRequest;
use Modules\Commerce\Http\Resources\ProductVariantResource;
use Modules\Commerce\Internal\Models\Product;
use Modules\Commerce\Internal\Models\ProductVariant;

final class ProductVariantController
{
    public function index(Product $product): JsonResponse
    {
        $variants = $product->variants()->with('stockLevels')->get();

        return response()->json(['data' => ProductVariantResource::collection($variants)]);
    }

    public function store(StoreProductVariantRequest $request, Product $product): JsonResponse
    {
        $validated = $request->validated();

        $variant = ProductVariant::create([
            'product_id' => $product->id,
            'label'      => $validated['label'],
            'reference'  => $validated['reference'],
            'active'     => true,
        ]);

        return response()->json(['data' => new ProductVariantResource($variant)], 201);
    }

    public function update(UpdateProductVariantRequest $request, ProductVariant $productVariant): JsonResponse
    {
        $validated = $request->validated();

        $productVariant->update($validated);

        return response()->json(['data' => new ProductVariantResource($productVariant->fresh() ?? $productVariant)]);
    }
}
