<?php

declare(strict_types=1);

namespace Modules\Commerce\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\Commerce\Http\Requests\StorePointOfSaleRequest;
use Modules\Commerce\Http\Requests\UpdatePointOfSaleRequest;
use Modules\Commerce\Http\Resources\PointOfSaleResource;
use Modules\Commerce\Internal\Models\OrganizationalUnit;
use Modules\Commerce\Internal\Models\PointOfSale;

final class PointOfSaleController
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $query = PointOfSale::with(['cashRegisters'])->orderBy('name');

        // Proprietaires and users with pos.write see every POS.
        // Gerants and vendeurs see only their assigned POS.
        if ($user !== null && ! $user->hasPermissionTo('pos.write')) {
            $assignedIds = DB::table('member_point_of_sale')
                ->where('member_id', $user->id)
                ->pluck('point_of_sale_id');

            $query->whereIn('id', $assignedIds);
        }

        return response()->json(['data' => PointOfSaleResource::collection($query->get())]);
    }

    public function store(StorePointOfSaleRequest $request): JsonResponse
    {
        $data = $request->validated();

        $unit = OrganizationalUnit::where('id', $data['organizational_unit_id'])->firstOrFail();

        $pos = PointOfSale::create([
            'name'                   => $data['name'],
            'organizational_unit_id' => $unit->id,
            'active'                 => true,
        ]);

        $freshPos = $pos->fresh()?->load('organizationalUnit');

        return response()->json(['data' => $freshPos !== null ? new PointOfSaleResource($freshPos) : null], 201);
    }

    public function show(PointOfSale $pointOfSale): JsonResponse
    {
        return response()->json(['data' => new PointOfSaleResource($pointOfSale->load('organizationalUnit', 'cashRegisters'))]);
    }

    public function update(UpdatePointOfSaleRequest $request, PointOfSale $pointOfSale): JsonResponse
    {
        $data = $request->validated();

        $pointOfSale->update($data);

        return response()->json(['data' => new PointOfSaleResource($pointOfSale->fresh() ?? $pointOfSale)]);
    }
}
