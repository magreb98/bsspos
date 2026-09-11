<?php

declare(strict_types=1);

namespace Modules\Commerce\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Modules\Commerce\Http\Data\StoreProductImageData;
use Modules\Commerce\Internal\Models\Product;
use Modules\Commerce\Internal\Models\ProductImage;

final class ProductImageController
{
    public function index(Product $product): JsonResponse
    {
        return response()->json([
            'data' => $product->images()->get()->toArray(),
        ]);
    }

    public function store(Request $request, Product $product): JsonResponse
    {
        $validated = $request->validate([
            'url'      => ['required', 'string', 'max:2048'],
            'position' => ['sometimes', 'integer', 'min:0'],
        ]);

        $position = $validated['position'] ?? (int) (($product->images()->max('position') ?? -1) + 1);

        $_ = StoreProductImageData::from($validated);

        $image = $product->images()->create([
            'url'      => $validated['url'],
            'position' => $position,
        ]);

        return response()->json(['data' => $image->toArray()], 201);
    }

    public function upload(Request $request, Product $product): JsonResponse
    {
        $request->validate([
            'image'    => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'position' => ['sometimes', 'integer', 'min:0'],
        ]);

        /** @var UploadedFile $file */
        $file = $request->file('image');
        $path = $file->store("products/{$product->id}", 'public');

        if ($path === false) {
            return response()->json([
                'code'    => 'UPLOAD_FAILED',
                'message' => 'Le fichier n\'a pas pu être enregistré.',
                'champ'   => 'image',
            ], 500);
        }

        $position = $request->filled('position')
            ? (int) $request->input('position')
            : (int) (($product->images()->max('position') ?? -1) + 1);

        $image = $product->images()->create([
            'url'      => $path,
            'position' => $position,
        ]);

        return response()->json(['data' => $image->toArray()], 201);
    }

    public function destroy(Product $product, ProductImage $image): JsonResponse
    {
        if ($image->product_id !== $product->id) {
            return response()->json([
                'code'    => 'IMAGE_NOT_FOUND',
                'message' => 'Cette image n\'appartient pas à ce produit.',
                'champ'   => null,
            ], 404);
        }

        $image->delete();

        return response()->json(null, 204);
    }

    public function reorder(Request $request, Product $product): JsonResponse
    {
        $validated = $request->validate([
            'order'   => ['required', 'array', 'min:1'],
            'order.*' => ['required', 'uuid'],
        ]);

        $ids = $validated['order'];
        $images = $product->images()->whereIn('id', $ids)->get()->keyBy('id');

        if ($images->count() !== count($ids)) {
            return response()->json([
                'code'    => 'IMAGE_NOT_FOUND',
                'message' => 'Une ou plusieurs images sont introuvables pour ce produit.',
                'champ'   => 'order',
            ], 404);
        }

        foreach ($ids as $position => $id) {
            $image = $images->get($id);
            if ($image !== null) {
                $image->update(['position' => $position]);
            }
        }

        return response()->json([
            'data' => $product->images()->get()->toArray(),
        ]);
    }
}
