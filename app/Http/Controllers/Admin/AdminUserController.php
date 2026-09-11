<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Control\AdminUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class AdminUserController
{
    public function index(): JsonResponse
    {
        $users = AdminUser::orderBy('name')->get(['id', 'name', 'email', 'active', 'last_connected_at', 'created_at']);

        return response()->json(['data' => $users->toArray()]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'     => ['required', 'string', 'max:255'],
            'email'    => ['required', 'email', 'unique:admin_users,email'],
            'password' => ['required', 'string', 'min:8'],
        ]);

        $admin = AdminUser::create($validated);

        return response()->json([
            'data' => [
                'id'    => $admin->id,
                'name'  => $admin->name,
                'email' => $admin->email,
            ],
        ], 201);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $admin = AdminUser::find($id);

        if ($admin === null) {
            return response()->json(['code' => 'NOT_FOUND', 'message' => 'Administrateur introuvable.'], 404);
        }

        $validated = $request->validate([
            'name'     => ['sometimes', 'string', 'max:255'],
            'email'    => ['sometimes', 'email', 'unique:admin_users,email,' . $id],
            'active'   => ['sometimes', 'boolean'],
            'password' => ['sometimes', 'string', 'min:8'],
        ]);

        $admin->update($validated);

        return response()->json([
            'data' => [
                'id'     => $admin->id,
                'name'   => $admin->name,
                'email'  => $admin->email,
                'active' => $admin->active,
            ],
        ]);
    }

    public function destroy(string $id): JsonResponse
    {
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
