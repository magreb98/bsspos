<?php

declare(strict_types=1);

namespace Modules\Commerce\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Commerce\Internal\Models\Family;

final class FamilyController
{
    public function index(): JsonResponse
    {
        $families = Family::query()->orderBy('name')->get();

        return response()->json(['data' => $families->toArray()]);
    }

    public function show(Family $family): JsonResponse
    {
        return response()->json(['data' => $family->toArray()]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'      => ['required', 'string', 'max:150'],
            'parent_id' => ['sometimes', 'nullable', 'uuid'],
        ]);

        if (isset($validated['parent_id'])) {
            $parent = Family::query()->whereKey($validated['parent_id'])->first();
            if ($parent === null) {
                return response()->json(['code' => 'PARENT_NOT_FOUND', 'message' => 'Famille parente introuvable.', 'champ' => 'parent_id'], 404);
            }
        }

        $family = Family::create(array_merge($validated, ['active' => true]));

        return response()->json(['data' => $family->toArray()], 201);
    }

    public function update(Request $request, Family $family): JsonResponse
    {
        $validated = $request->validate([
            'name'      => ['sometimes', 'string', 'max:150'],
            'parent_id' => ['sometimes', 'nullable', 'uuid'],
            'active'    => ['sometimes', 'boolean'],
        ]);

        $family->update($validated);

        return response()->json(['data' => $family->fresh()?->toArray() ?? $family->toArray()]);
    }
}
