<?php

declare(strict_types=1);

namespace Modules\Commerce\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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

        return response()->json(['data' => $units]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'      => ['required', 'string', 'max:255'],
            'parent_id' => ['sometimes', 'nullable', 'uuid', 'exists:organizational_units,id'],
        ]);

        $unit = OrganizationalUnit::create([
            'name'      => $validated['name'],
            'parent_id' => $validated['parent_id'] ?? null,
            'active'    => true,
        ]);

        return response()->json(['data' => $unit->fresh()], 201);
    }

    public function show(OrganizationalUnit $organizationalUnit): JsonResponse
    {
        return response()->json([
            'data' => $organizationalUnit->load('children', 'pointsOfSale.cashRegisters'),
        ]);
    }

    public function update(Request $request, OrganizationalUnit $organizationalUnit): JsonResponse
    {
        $validated = $request->validate([
            'name'   => ['sometimes', 'string', 'max:255'],
            'active' => ['sometimes', 'boolean'],
        ]);

        $organizationalUnit->update($validated);

        return response()->json(['data' => $organizationalUnit->fresh()]);
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
