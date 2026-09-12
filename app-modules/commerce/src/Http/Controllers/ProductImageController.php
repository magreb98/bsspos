<?php

declare(strict_types=1);

namespace Modules\Commerce\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\UploadedFile;
use Modules\Commerce\Http\Requests\ReorderProductImagesRequest;
use Modules\Commerce\Http\Requests\StoreProductImageRequest;
use Modules\Commerce\Http\Requests\UploadProductImageRequest;
use Modules\Commerce\Http\Resources\ProductImageResource;
use Modules\Commerce\Internal\Models\Product;
use Modules\Commerce\Internal\Models\ProductImage;

final class ProductImageController
{
    public function index(Product $product): JsonResponse
    {
        return response()->json([
            'data' => ProductImageResource::collection($product->images()->get()),
        ]);
    }

    public function store(StoreProductImageRequest $request, Product $product): JsonResponse
    {
        $validated = $request->validated();

        $position = $validated['position'] ?? (int) (($product->images()->max('position') ?? -1) + 1);

        $image = $product->images()->create([
            'url'      => $validated['url'],
            'position' => $position,
        ]);

        return response()->json(['data' => new ProductImageResource($image)], 201);
    }

    public function upload(UploadProductImageRequest $request, Product $product): JsonResponse
    {
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

        return response()->json(['data' => new ProductImageResource($image)], 201);
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

    public function reorder(ReorderProductImagesRequest $request, Product $product): JsonResponse
    {
        $validated = $request->validated();

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
            'data' => ProductImageResource::collection($product->images()->get()),
        ]);
    }
}
