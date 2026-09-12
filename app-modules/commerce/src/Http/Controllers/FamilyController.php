<?php

declare(strict_types=1);

namespace Modules\Commerce\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Modules\Commerce\Http\Requests\StoreFamilyRequest;
use Modules\Commerce\Http\Requests\UpdateFamilyRequest;
use Modules\Commerce\Http\Resources\FamilyResource;
use Modules\Commerce\Internal\Models\Family;

final class FamilyController
{
    public function index(): JsonResponse
    {
        $families = Family::query()->orderBy('name')->get();

        return response()->json(['data' => FamilyResource::collection($families)]);
    }

    public function show(Family $family): JsonResponse
    {
        return response()->json(['data' => new FamilyResource($family)]);
    }

    public function store(StoreFamilyRequest $request): JsonResponse
    {
        $validated = $request->validated();

        if (isset($validated['parent_id'])) {
            $parent = Family::query()->whereKey($validated['parent_id'])->first();
            if ($parent === null) {
                return response()->json(['code' => 'PARENT_NOT_FOUND', 'message' => 'Famille parente introuvable.', 'champ' => 'parent_id'], 404);
            }
        }

        $family = Family::create(array_merge($validated, ['active' => true]));

        return response()->json(['data' => new FamilyResource($family)], 201);
    }

    public function update(UpdateFamilyRequest $request, Family $family): JsonResponse
    {
        $validated = $request->validated();

        $family->update($validated);

        return response()->json(['data' => new FamilyResource($family->fresh() ?? $family)]);
    }
}
