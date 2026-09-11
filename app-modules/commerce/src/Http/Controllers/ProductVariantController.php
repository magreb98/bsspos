<?php

declare(strict_types=1);

namespace Modules\Commerce\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Commerce\Internal\Models\Product;
use Modules\Commerce\Internal\Models\ProductVariant;

final class ProductVariantController
{
    public function index(Product $product): JsonResponse
    {
        $variants = $product->variants()->with('stockLevels')->get();

        return response()->json(['data' => $variants->toArray()]);
    }

    public function store(Request $request, Product $product): JsonResponse
    {
        $validated = $request->validate([
            'label'     => ['required', 'string', 'max:255'],
            'reference' => ['required', 'string', 'max:100', 'unique:product_variants,reference'],
        ]);

        $variant = ProductVariant::create([
            'product_id' => $product->id,
            'label'      => $validated['label'],
            'reference'  => $validated['reference'],
            'active'     => true,
        ]);

        return response()->json(['data' => $variant->toArray()], 201);
    }

    public function update(Request $request, ProductVariant $productVariant): JsonResponse
    {
        $validated = $request->validate([
            'label'  => ['sometimes', 'string', 'max:255'],
            'active' => ['sometimes', 'boolean'],
        ]);

        $productVariant->update($validated);

        return response()->json(['data' => $productVariant->fresh()?->toArray() ?? $productVariant->toArray()]);
    }
}
