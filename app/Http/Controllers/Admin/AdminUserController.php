<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Control\AdminUser;
use App\Http\Controllers\Admin\Concerns\RequiresSuperAdmin;
use App\Http\Requests\Admin\StoreAdminUserRequest;
use App\Http\Requests\Admin\UpdateAdminUserRequest;
use App\Http\Resources\Admin\AdminUserResource;
use App\Http\Resources\Admin\AdminUserStoreResource;
use App\Http\Resources\Admin\AdminUserUpdateResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class AdminUserController
{
    use RequiresSuperAdmin;

    public function index(): JsonResponse
    {
        $users = AdminUser::orderBy('name')->get(['id', 'name', 'email', 'active', 'is_super_admin', 'last_connected_at', 'created_at']);

        return response()->json(['data' => AdminUserResource::collection($users)]);
    }

    public function store(StoreAdminUserRequest $request): JsonResponse
    {
        $this->assertActingAdminIsSuperAdmin($request);

        $validated = $request->validated();

        $admin = AdminUser::create([
            ...$validated,
            'is_super_admin' => $validated['is_super_admin'] ?? false,
        ]);

        return response()->json(['data' => new AdminUserStoreResource($admin)], 201);
    }

    public function update(UpdateAdminUserRequest $request, string $id): JsonResponse
    {
        $this->assertActingAdminIsSuperAdmin($request);

        $admin = AdminUser::find($id);

        if ($admin === null) {
            return response()->json(['code' => 'NOT_FOUND', 'message' => 'Administrateur introuvable.'], 404);
        }

        $validated = $request->validated();

        $admin->update($validated);

        return response()->json(['data' => new AdminUserUpdateResource($admin)]);
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $this->assertActingAdminIsSuperAdmin($request);

        $admin = AdminUser::find($id);

        if ($admin === null) {
            return response()->json(['code' => 'NOT_FOUND', 'message' => 'Administrateur introuvable.'], 404);
        }

        // Revoke all tokens before deleting
        $admin->tokens()->delete();
        $admin->delete();

        return response()->json([], 204);
    }
}
