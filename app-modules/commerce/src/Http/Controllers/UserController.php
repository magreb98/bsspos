<?php

declare(strict_types=1);

namespace Modules\Commerce\Http\Controllers;

use App\Platform\Identity\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Modules\Commerce\Internal\Models\PointOfSale;

final class UserController
{
    public function index(): JsonResponse
    {
        $members = User::query()
            ->orderBy('first_name')
            ->get();

        $memberIds = $members->pluck('id')->all();

        // Bulk-load POS assignments to avoid N+1
        $posMap = DB::table('member_point_of_sale')
            ->join('points_of_sale', 'member_point_of_sale.point_of_sale_id', '=', 'points_of_sale.id')
            ->whereIn('member_point_of_sale.member_id', $memberIds)
            ->select('member_point_of_sale.member_id', 'points_of_sale.id', 'points_of_sale.name')
            ->get()
            ->groupBy('member_id');

        $data = $members->map(function (User $m) use ($posMap): array {
            $rawRole = $m->getRoleNames()->first() ?? 'vendeur';
            $role = match ($rawRole) {
                'gérant', 'gerant' => 'gerant',
                'proprietaire'     => 'proprietaire',
                default            => 'vendeur',
            };

            $posList = ($posMap->get($m->id) ?? collect())
                ->map(fn ($row) => ['id' => $row->id, 'name' => $row->name])
                ->values()
                ->all();

            return [
                'id'             => $m->id,
                'name'           => trim($m->first_name . ' ' . $m->last_name),
                'phone'          => $m->phone,
                'role'           => $role,
                'points_of_sale' => $posList,
                'active'         => $m->active,
                'last_seen_at'   => $m->last_connected_at?->toIso8601String(),
            ];
        });

        return response()->json(['data' => $data]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'              => ['required', 'string', 'max:200'],
            'phone'             => ['required', 'string', 'max:20', 'unique:members,phone'],
            'role'              => ['required', 'string', 'in:vendeur,gerant,gérant,proprietaire'],
            'point_of_sale_ids' => ['nullable', 'array'],
            'point_of_sale_ids.*' => ['string', 'exists:points_of_sale,id'],
        ]);

        $parts     = explode(' ', trim($validated['name']), 2);
        $firstName = $parts[0];
        $lastName  = $parts[1] ?? '';

        $member = User::create([
            'first_name' => $firstName,
            'last_name'  => $lastName,
            'phone'      => $validated['phone'],
            'password'   => Hash::make($validated['phone']),
            'active'     => true,
        ]);

        $role = match ($validated['role']) {
            'gerant', 'gérant' => 'gérant',
            'proprietaire'     => 'proprietaire',
            default            => 'vendeur',
        };
        $member->assignRole($role);

        $posIds  = $validated['point_of_sale_ids'] ?? [];
        if ($posIds !== []) {
            $rows = array_map(fn (string $id): array => [
                'member_id'       => $member->id,
                'point_of_sale_id' => $id,
                'created_at'      => now(),
                'updated_at'      => now(),
            ], $posIds);
            DB::table('member_point_of_sale')->insert($rows);
        }

        $posList = PointOfSale::whereIn('id', $posIds)->get(['id', 'name'])
            ->map(fn ($p) => ['id' => $p->id, 'name' => $p->name])
            ->values()
            ->all();

        return response()->json([
            'data' => [
                'id'             => $member->id,
                'name'           => trim($member->first_name . ' ' . $member->last_name),
                'phone'          => $member->phone,
                'role'           => $validated['role'],
                'points_of_sale' => $posList,
                'active'         => true,
                'last_seen_at'   => null,
            ],
        ], 201);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $member = User::findOrFail($id);

        $validated = $request->validate([
            'role'   => ['sometimes', 'string', 'in:vendeur,gerant,gérant,proprietaire'],
            'active' => ['sometimes', 'boolean'],
        ]);

        if (isset($validated['role'])) {
            $role = match ($validated['role']) {
                'gerant', 'gérant' => 'gérant',
                'proprietaire'     => 'proprietaire',
                default            => 'vendeur',
            };
            $member->syncRoles([$role]);
        }

        if (isset($validated['active'])) {
            $member->update(['active' => $validated['active']]);
        }

        $rawCurrent  = $member->getRoleNames()->first() ?? 'vendeur';
        $currentRole = match ($rawCurrent) {
            'gérant', 'gerant' => 'gerant',
            'proprietaire'     => 'proprietaire',
            default            => 'vendeur',
        };

        $posList = DB::table('member_point_of_sale')
            ->join('points_of_sale', 'member_point_of_sale.point_of_sale_id', '=', 'points_of_sale.id')
            ->where('member_point_of_sale.member_id', $member->id)
            ->select('points_of_sale.id', 'points_of_sale.name')
            ->get()
            ->map(fn ($row) => ['id' => $row->id, 'name' => $row->name])
            ->values()
            ->all();

        return response()->json([
            'data' => [
                'id'             => $member->id,
                'name'           => trim($member->first_name . ' ' . $member->last_name),
                'phone'          => $member->phone,
                'role'           => $currentRole,
                'points_of_sale' => $posList,
                'active'         => $member->active,
                'last_seen_at'   => $member->last_connected_at?->toIso8601String(),
            ],
        ]);
    }

    /** GET /commerce/users/{id}/points-of-sale */
    public function listPos(string $id): JsonResponse
    {
        User::findOrFail($id);

        $data = DB::table('member_point_of_sale')
            ->join('points_of_sale', 'member_point_of_sale.point_of_sale_id', '=', 'points_of_sale.id')
            ->where('member_point_of_sale.member_id', $id)
            ->select('points_of_sale.id', 'points_of_sale.name', 'points_of_sale.active')
            ->get();

        return response()->json(['data' => $data]);
    }

    /** POST /commerce/users/{id}/points-of-sale  body: { point_of_sale_id } */
    public function assignPos(Request $request, string $id): JsonResponse
    {
        User::findOrFail($id);

        $validated = $request->validate([
            'point_of_sale_id' => ['required', 'string', 'exists:points_of_sale,id'],
        ]);

        $posId = $validated['point_of_sale_id'];

        $exists = DB::table('member_point_of_sale')
            ->where('member_id', $id)
            ->where('point_of_sale_id', $posId)
            ->exists();

        if (! $exists) {
            DB::table('member_point_of_sale')->insert([
                'member_id'       => $id,
                'point_of_sale_id' => $posId,
                'created_at'      => now(),
                'updated_at'      => now(),
            ]);
        }

        return response()->json(['message' => 'Point de vente affecté.'], 201);
    }

    /** DELETE /commerce/users/{id}/points-of-sale/{posId} */
    public function unassignPos(string $id, string $posId): JsonResponse
    {
        User::findOrFail($id);

        DB::table('member_point_of_sale')
            ->where('member_id', $id)
            ->where('point_of_sale_id', $posId)
            ->delete();

        return response()->json(['message' => 'Affectation supprimée.']);
    }
}
