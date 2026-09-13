<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Control\AdminToken;
use App\Control\AdminUser;
use App\Http\Requests\Admin\ChangePasswordRequest;
use App\Http\Requests\Admin\LoginRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

final class AuthController
{
    public function login(LoginRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $admin = AdminUser::where('email', $validated['email'])->first();

        if ($admin === null || ! $admin->checkPassword($validated['password'])) {
            return response()->json([
                'code'    => 'INVALID_CREDENTIALS',
                'message' => 'Identifiants invalides.',
            ], 401);
        }

        if (! $admin->isActive()) {
            return response()->json([
                'code'    => 'ACCOUNT_INACTIVE',
                'message' => 'Ce compte est désactivé.',
            ], 403);
        }

        $rawToken = Str::random(64);

        AdminToken::create([
            'admin_user_id' => $admin->id,
            'name'          => 'Web Login',
            'token'         => hash('sha256', $rawToken),
            'expires_at'    => now()->addDays(30),
        ]);

        $admin->update(['last_connected_at' => now()]);

        return response()->json([
            'token' => $rawToken,
            'user'  => [
                'id'             => $admin->id,
                'name'           => $admin->name,
                'email'          => $admin->email,
                'is_super_admin' => $admin->is_super_admin,
            ],
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $authHeader = $request->header('Authorization', '');
        $rawToken   = substr((string) $authHeader, 7);

        if ($rawToken !== '') {
            AdminToken::where('token', hash('sha256', $rawToken))->delete();
        }

        return response()->json([]);
    }

    public function me(Request $request): JsonResponse
    {
        $admin = $request->attributes->get('admin_user');

        return response()->json([
            'data' => [
                'id'                 => $admin->id,
                'name'               => $admin->name,
                'email'              => $admin->email,
                'is_super_admin'     => $admin->is_super_admin,
                'last_connected_at'  => $admin->last_connected_at?->toIso8601String(),
            ],
        ]);
    }

    public function changePassword(ChangePasswordRequest $request): JsonResponse
    {
        /** @var AdminUser $admin */
        $admin = $request->attributes->get('admin_user');

        $validated = $request->validated();

        if (! $admin->checkPassword($validated['current_password'])) {
            return response()->json([
                'code'    => 'INVALID_CURRENT_PASSWORD',
                'message' => 'Mot de passe actuel incorrect.',
                'champ'   => 'current_password',
            ], 422);
        }

        // AdminUser casts 'password' => 'hashed', so the model hashes this
        // for us — calling Hash::make() here too would double-hash.
        $admin->update(['password' => $validated['new_password']]);

        return response()->json(['message' => 'Mot de passe mis à jour.']);
    }
}
