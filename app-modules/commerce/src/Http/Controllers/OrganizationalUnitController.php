<?php

declare(strict_types=1);

namespace Modules\Commerce\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Modules\Commerce\Http\Requests\StoreOrganizationalUnitRequest;
use Modules\Commerce\Http\Requests\UpdateOrganizationalUnitRequest;
use Modules\Commerce\Http\Resources\OrganizationalUnitResource;
use Modules\Commerce\Internal\Models\OrganizationalUnit;

final class OrganizationalUnitController
{
    public function index(): JsonResponse
    {
        $units = OrganizationalUnit::with('children')
            ->whereNull('parent_id')
            ->where('active', true)
            ->orderBy('name')
            ->get();

        return response()->json(['data' => OrganizationalUnitResource::collection($units)]);
    }

    public function store(StoreOrganizationalUnitRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $unit = OrganizationalUnit::create([
            'name'      => $validated['name'],
            'parent_id' => $validated['parent_id'] ?? null,
            'active'    => true,
        ]);

        return response()->json(['data' => new OrganizationalUnitResource($unit->fresh() ?? $unit)], 201);
    }

    public function show(OrganizationalUnit $organizationalUnit): JsonResponse
    {
        return response()->json([
            'data' => new OrganizationalUnitResource($organizationalUnit->load('children', 'pointsOfSale.cashRegisters')),
        ]);
    }

    public function update(UpdateOrganizationalUnitRequest $request, OrganizationalUnit $organizationalUnit): JsonResponse
    {
        $validated = $request->validated();

        $organizationalUnit->update($validated);

        return response()->json(['data' => new OrganizationalUnitResource($organizationalUnit->fresh() ?? $organizationalUnit)]);
    }

    public function destroy(OrganizationalUnit $organizationalUnit): JsonResponse
    {
        if ($organizationalUnit->pointsOfSale()->exists()) {
            return response()->json([
                'code'    => 'UNIT_HAS_POS',
                'message' => 'Impossible de supprimer une unité liée à des points de vente.',
                'champ'   => null,
            ], 409);
        }

        $organizationalUnit->update(['active' => false]);

        return response()->json([], 204);
    }
}
