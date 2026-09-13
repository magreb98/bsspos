<?php

declare(strict_types=1);

namespace Modules\Commerce\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Commerce\Http\Requests\StoreProductRequest;
use Modules\Commerce\Http\Requests\UpdateProductRequest;
use Modules\Commerce\Http\Resources\ProductResource;
use Modules\Commerce\Internal\Models\Family;
use Modules\Commerce\Internal\Models\Product;

final class ProductController
{
    public function index(Request $request): JsonResponse
    {
        $query = Product::query()->with(['images', 'family']);

        if ($request->filled('family_id')) {
            $query->where('family_id', $request->input('family_id'));
        }

        if ($request->filled('search')) {
            $term = (string) $request->input('search');
            $query->where(static function ($q) use ($term): void {
                $q->where('label', 'like', "%{$term}%")
                  ->orWhere('reference', 'like', "%{$term}%");
            });
        }

        if ($request->filled('reference')) {
            $query->where('reference', $request->input('reference'));
        }

        if ($request->boolean('active', true)) {
            $query->where('active', true);
        }

        $products = $query->orderBy('label')->get();

        return response()->json(['data' => ProductResource::collection($products)]);
    }

    public function show(Product $product): JsonResponse
    {
        $product->load('images', 'family');

        return response()->json(['data' => new ProductResource($product)]);
    }

    public function store(StoreProductRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $family = Family::query()->whereKey($validated['family_id'])->first();
        if ($family === null) {
            return response()->json(['code' => 'FAMILY_NOT_FOUND', 'message' => 'Famille introuvable.', 'champ' => 'family_id'], 404);
        }

        $product = Product::create(array_merge($validated, ['active' => true]));

        return response()->json(['data' => new ProductResource($product)], 201);
    }

    public function update(UpdateProductRequest $request, Product $product): JsonResponse
    {
        $validated = $request->validated();

        $product->update($validated);

        return response()->json(['data' => new ProductResource($product->fresh() ?? $product)]);
    }
}
