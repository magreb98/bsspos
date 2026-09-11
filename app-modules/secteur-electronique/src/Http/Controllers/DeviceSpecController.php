<?php

declare(strict_types=1);

namespace Modules\SecteurElectronique\Http\Controllers;

use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Modules\Commerce\Internal\Models\Product;
use Modules\SecteurElectronique\Enums\DeviceCategory;
use Modules\SecteurElectronique\Http\Data\StoreDeviceSpecData;
use Modules\SecteurElectronique\Http\Data\UpdateDeviceSpecData;
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
            'data' => array_map(fn (DeviceSpec $spec) => $spec->toArray(), $paginator->items()),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page'     => $paginator->perPage(),
                'total'        => $paginator->total(),
                'last_page'    => $paginator->lastPage(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'product_id'         => ['required', 'uuid'],
            'category'           => ['required', 'string'],
            'brand'              => ['required', 'string', 'max:100'],
            'model'              => ['required', 'string', 'max:150'],
            'warranty_months'    => ['required', 'integer', 'min:1'],
            'imei_required'      => ['sometimes', 'boolean'],
            'screen_size_inches' => ['sometimes', 'nullable', 'numeric'],
            'screen_resolution'  => ['sometimes', 'nullable', 'string', 'max:50'],
            'panel_type'         => ['sometimes', 'nullable', 'string', 'max:50'],
            'processor'          => ['sometimes', 'nullable', 'string', 'max:100'],
            'ram_gb'             => ['sometimes', 'nullable', 'integer', 'min:1'],
            'storage_gb'         => ['sometimes', 'nullable', 'integer', 'min:1'],
            'bluetooth'          => ['sometimes', 'nullable', 'boolean'],
            'wifi'               => ['sometimes', 'nullable', 'boolean'],
            'nfc'                => ['sometimes', 'nullable', 'boolean'],
            'cellular_network'   => ['sometimes', 'nullable', 'string', 'max:50'],
            'battery_mah'        => ['sometimes', 'nullable', 'integer', 'min:1'],
            'main_camera_mp'     => ['sometimes', 'nullable', 'numeric'],
            'power_watts'        => ['sometimes', 'nullable', 'numeric'],
            'operating_system'   => ['sometimes', 'nullable', 'string', 'max:100'],
            'weight_grams'       => ['sometimes', 'nullable', 'integer'],
            'color'              => ['sometimes', 'nullable', 'string', 'max:50'],
            'model_year'         => ['sometimes', 'nullable', 'integer'],
            'additional_specs'   => ['sometimes', 'nullable', 'array'],
        ]);

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

        // B1: D6 compliance — hydrate DTO from validated payload
        $_ = StoreDeviceSpecData::from(
            array_filter($validated, static fn (mixed $v): bool => $v !== null)
        );

        try {
            $spec = $this->service->attach($product, $validated);
        } catch (DomainException $e) {
            return response()->json([
                'code'    => 'DEVICE_SPEC_ALREADY_EXISTS',
                'message' => $e->getMessage(),
                'champ'   => null,
            ], 409);
        }

        return response()->json(['data' => $spec->toArray()], 201);
    }

    public function show(DeviceSpec $deviceSpec): JsonResponse
    {
        return response()->json(['data' => $deviceSpec->toArray()]);
    }

    public function destroy(DeviceSpec $deviceSpec): JsonResponse
    {
        $deviceSpec->delete();

        return response()->json(null, 204);
    }

    public function update(Request $request, DeviceSpec $deviceSpec): JsonResponse
    {
        $validated = $request->validate([
            'category'           => ['sometimes', 'string'],
            'brand'              => ['sometimes', 'string', 'max:100'],
            'model'              => ['sometimes', 'string', 'max:150'],
            'warranty_months'    => ['sometimes', 'integer', 'min:1'],
            'imei_required'      => ['sometimes', 'boolean'],
            'screen_size_inches' => ['sometimes', 'nullable', 'numeric'],
            'screen_resolution'  => ['sometimes', 'nullable', 'string', 'max:50'],
            'panel_type'         => ['sometimes', 'nullable', 'string', 'max:50'],
            'processor'          => ['sometimes', 'nullable', 'string', 'max:100'],
            'ram_gb'             => ['sometimes', 'nullable', 'integer', 'min:1'],
            'storage_gb'         => ['sometimes', 'nullable', 'integer', 'min:1'],
            'bluetooth'          => ['sometimes', 'nullable', 'boolean'],
            'wifi'               => ['sometimes', 'nullable', 'boolean'],
            'nfc'                => ['sometimes', 'nullable', 'boolean'],
            'cellular_network'   => ['sometimes', 'nullable', 'string', 'max:50'],
            'battery_mah'        => ['sometimes', 'nullable', 'integer', 'min:1'],
            'main_camera_mp'     => ['sometimes', 'nullable', 'numeric'],
            'power_watts'        => ['sometimes', 'nullable', 'numeric'],
            'operating_system'   => ['sometimes', 'nullable', 'string', 'max:100'],
            'weight_grams'       => ['sometimes', 'nullable', 'integer'],
            'color'              => ['sometimes', 'nullable', 'string', 'max:50'],
            'model_year'         => ['sometimes', 'nullable', 'integer'],
            'additional_specs'   => ['sometimes', 'nullable', 'array'],
        ]);

        // B3: Explicit enum validation for category when supplied
        if (array_key_exists('category', $validated)) {
            $categoryEnum = DeviceCategory::tryFrom($validated['category']);
            if ($categoryEnum === null) {
                throw ValidationException::withMessages([
                    'category' => 'La catégorie fournie n\'est pas reconnue.',
                ]);
            }
        }

        // B1: D6 compliance — hydrate DTO from validated payload
        $_ = UpdateDeviceSpecData::from(
            array_filter($validated, static fn (mixed $v): bool => $v !== null)
        );

        $updated = $this->service->update($deviceSpec, $validated);

        return response()->json(['data' => $updated->toArray()]);
    }
}
