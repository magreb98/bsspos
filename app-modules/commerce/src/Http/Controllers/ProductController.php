<?php

declare(strict_types=1);

namespace Modules\Commerce\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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

        return response()->json(['data' => $products->toArray()]);
    }

    public function show(Product $product): JsonResponse
    {
        $product->load('images');

        return response()->json(['data' => $product->toArray()]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'reference'     => ['required', 'string', 'max:100'],
            'label'         => ['required', 'string', 'max:255'],
            'family_id'     => ['required', 'uuid'],
            'selling_price' => ['required', 'integer', 'min:0'],
            'vat_rate'      => ['required', 'numeric', 'min:0', 'max:100'],
            'granularity'   => ['required', 'string', 'in:quantity,variant,serial,batch,service'],
            'attributes'    => ['sometimes', 'nullable', 'array'],
        ]);

        $family = Family::query()->whereKey($validated['family_id'])->first();
        if ($family === null) {
            return response()->json(['code' => 'FAMILY_NOT_FOUND', 'message' => 'Famille introuvable.', 'champ' => 'family_id'], 404);
        }

        $product = Product::create(array_merge($validated, ['active' => true]));

        return response()->json(['data' => $product->toArray()], 201);
    }

    public function update(Request $request, Product $product): JsonResponse
    {
        $validated = $request->validate([
            'label'         => ['sometimes', 'string', 'max:255'],
            'family_id'     => ['sometimes', 'uuid'],
            'selling_price' => ['sometimes', 'integer', 'min:0'],
            'vat_rate'      => ['sometimes', 'numeric', 'min:0', 'max:100'],
            'granularity'   => ['sometimes', 'string', 'in:quantity,variant,serial,batch,service'],
            'attributes'    => ['sometimes', 'nullable', 'array'],
            'active'        => ['sometimes', 'boolean'],
        ]);

        $product->update($validated);

        return response()->json(['data' => $product->fresh()?->toArray() ?? $product->toArray()]);
    }
}
