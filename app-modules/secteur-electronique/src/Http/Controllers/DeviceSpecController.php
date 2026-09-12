<?php

declare(strict_types=1);

namespace Modules\SecteurElectronique\Http\Controllers;

use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Modules\Commerce\Internal\Models\Product;
use Modules\SecteurElectronique\Enums\DeviceCategory;
use Modules\SecteurElectronique\Http\Requests\StoreDeviceSpecRequest;
use Modules\SecteurElectronique\Http\Requests\UpdateDeviceSpecRequest;
use Modules\SecteurElectronique\Http\Resources\DeviceSpecResource;
use Modules\SecteurElectronique\Models\DeviceSpec;
use Modules\SecteurElectronique\Services\DeviceSpecService;

final class DeviceSpecController
{
    public function __construct(
        private readonly DeviceSpecService $service,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $query = DeviceSpec::query();

        if ($request->filled('product_id')) {
            $query->where('product_id', $request->input('product_id'));
        }

        $paginator = $query->paginate(15);

        return response()->json([
            'data' => array_map(fn (DeviceSpec $spec) => new DeviceSpecResource($spec), $paginator->items()),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page'     => $paginator->perPage(),
                'total'        => $paginator->total(),
                'last_page'    => $paginator->lastPage(),
            ],
        ]);
    }

    public function store(StoreDeviceSpecRequest $request): JsonResponse
    {
        $validated = $request->validated();

        // B3: Explicit enum validation — error surfaces via global ValidationException handler
        $categoryEnum = DeviceCategory::tryFrom($validated['category']);
        if ($categoryEnum === null) {
            throw ValidationException::withMessages([
                'category' => 'La catégorie fournie n\'est pas reconnue.',
            ]);
        }

        // Deduce imei_required from the category enum if not explicitly provided
        if (! array_key_exists('imei_required', $validated)) {
            $validated['imei_required'] = $categoryEnum->requiresImei();
        }

        // B2: Manual product lookup — makes PRODUCT_NOT_FOUND code path reachable
        $product = Product::query()->whereKey($validated['product_id'])->first();
        if ($product === null) {
            return response()->json([
                'code'    => 'PRODUCT_NOT_FOUND',
                'message' => 'Ce produit n\'existe pas.',
                'champ'   => 'product_id',
            ], 404);
        }

        try {
            $spec = $this->service->attach($product, $validated);
        } catch (DomainException $e) {
            return response()->json([
                'code'    => 'DEVICE_SPEC_ALREADY_EXISTS',
                'message' => $e->getMessage(),
                'champ'   => null,
            ], 409);
        }

        return response()->json(['data' => new DeviceSpecResource($spec)], 201);
    }

    public function show(DeviceSpec $deviceSpec): JsonResponse
    {
        return response()->json(['data' => new DeviceSpecResource($deviceSpec)]);
    }

    public function destroy(DeviceSpec $deviceSpec): JsonResponse
    {
        $deviceSpec->delete();

        return response()->json(null, 204);
    }

    public function update(UpdateDeviceSpecRequest $request, DeviceSpec $deviceSpec): JsonResponse
    {
        $validated = $request->validated();

        // B3: Explicit enum validation for category when supplied
        if (array_key_exists('category', $validated)) {
            $categoryEnum = DeviceCategory::tryFrom($validated['category']);
            if ($categoryEnum === null) {
                throw ValidationException::withMessages([
                    'category' => 'La catégorie fournie n\'est pas reconnue.',
                ]);
            }
        }

        $updated = $this->service->update($deviceSpec, $validated);

        return response()->json(['data' => new DeviceSpecResource($updated)]);
    }
}
