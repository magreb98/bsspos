<?php

declare(strict_types=1);

namespace Modules\SecteurElectronique\Http\Controllers;

use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Commerce\Internal\Models\Product;
use Modules\SecteurElectronique\Enums\SerialStatus;
use Modules\SecteurElectronique\Http\Requests\StoreSerialUnitRequest;
use Modules\SecteurElectronique\Http\Resources\SerialUnitResource;
use Modules\SecteurElectronique\Models\SerialUnit;
use Modules\SecteurElectronique\Services\SerialService;

final class SerialUnitController
{
    public function __construct(
        private readonly SerialService $service,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $query = SerialUnit::query()->orderBy('created_at');

        if ($request->filled('product_id')) {
            $query->where('product_id', $request->input('product_id'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $paginator = $query->paginate(15);

        return response()->json([
            'data' => array_map(fn (SerialUnit $u) => new SerialUnitResource($u), $paginator->items()),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page'     => $paginator->perPage(),
                'total'        => $paginator->total(),
                'last_page'    => $paginator->lastPage(),
            ],
        ]);
    }

    public function store(StoreSerialUnitRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $product = Product::query()->whereKey($validated['product_id'])->first();
        if ($product === null) {
            return response()->json([
                'code'    => 'PRODUCT_NOT_FOUND',
                'message' => 'Ce produit n\'existe pas.',
                'champ'   => null,
            ], 404);
        }

        try {
            $unit = $this->service->register($product, $validated['serial_number']);
        } catch (DomainException) {
            return response()->json([
                'code'    => 'SERIAL_NUMBER_DUPLICATE',
                'message' => 'Ce numéro de série existe déjà pour ce produit.',
                'champ'   => 'serial_number',
            ], 409);
        }

        return response()->json(['data' => new SerialUnitResource($unit)], 201);
    }

    public function show(SerialUnit $serialUnit): JsonResponse
    {
        return response()->json(['data' => new SerialUnitResource($serialUnit)]);
    }

    public function destroy(SerialUnit $serialUnit): JsonResponse
    {
        if ($serialUnit->status !== SerialStatus::Available) {
            return response()->json([
                'code'    => 'SERIAL_UNIT_NOT_DELETABLE',
                'message' => 'Seules les unités disponibles peuvent être supprimées.',
                'champ'   => null,
            ], 409);
        }

        $serialUnit->delete();

        return response()->json(null, 204);
    }
}
