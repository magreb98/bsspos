<?php

declare(strict_types=1);

namespace Modules\Commerce\Http\Controllers;

use App\Platform\Identity\Models\MemberToken;
use App\Platform\Identity\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Modules\Commerce\Http\Requests\ChangePasswordRequest;
use Modules\Commerce\Http\Requests\LoginRequest;

final class AuthController
{
    public function login(LoginRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $member = User::where('phone', $validated['phone'])->first();

        if ($member === null || ! Hash::check($validated['password'], $member->password)) {
            return response()->json([
                'code'    => 'INVALID_CREDENTIALS',
                'message' => 'Identifiants invalides.',
            ], 401);
        }

        if (! $member->isActive()) {
            return response()->json([
                'code'    => 'ACCOUNT_INACTIVE',
                'message' => 'Ce compte est désactivé.',
            ], 403);
        }

        $rawToken = Str::random(64);

        MemberToken::create([
            'member_id'  => $member->id,
            'name'       => 'Web Login',
            'token'      => hash('sha256', $rawToken),
            'expires_at' => now()->addDays(30),
        ]);

        $member->withoutAuditing(fn () => $member->update(['last_connected_at' => now()]));

        $roles = $member->getRoleNames();
        $role  = $roles->first() ?? 'vendeur';

        return response()->json([
            'data' => [
                'token' => $rawToken,
                'user'  => [
                    'id'                    => $member->id,
                    'name'                  => trim($member->first_name . ' ' . $member->last_name),
                    'phone'                 => $member->phone,
                    'role'                  => $role,
                    'must_change_password'  => $member->must_change_password,
                ],
            ],
        ]);
    }

    public function changePassword(ChangePasswordRequest $request): JsonResponse
    {
        /** @var User $member */
        $member = $request->user();

        $validated = $request->validated();

        if (! Hash::check($validated['current_password'], $member->password)) {
            return response()->json([
                'code'    => 'INVALID_CURRENT_PASSWORD',
                'message' => 'Mot de passe actuel incorrect.',
                'champ'   => 'current_password',
            ], 422);
        }

        $member->withoutAuditing(fn () => $member->update([
            'password'              => Hash::make($validated['new_password']),
            'must_change_password'  => false,
        ]));

        return response()->json(['message' => 'Mot de passe mis à jour.']);
    }

    public function logout(Request $request): JsonResponse
    {
        $authHeader = $request->header('Authorization', '');
        $rawToken   = substr((string) $authHeader, 7);

        if ($rawToken !== '') {
            MemberToken::where('token', hash('sha256', $rawToken))->delete();
        }

        return response()->json([], 204);
    }

    public function me(Request $request): JsonResponse
    {
        /** @var User $member */
        $member = $request->user();
        $role   = $member->getRoleNames()->first() ?? 'vendeur';

        return response()->json([
            'data' => [
                'id'                => $member->id,
                'name'              => trim($member->first_name . ' ' . $member->last_name),
                'phone'             => $member->phone,
                'role'              => $role,
                'last_connected_at' => $member->last_connected_at?->toIso8601String(),
            ],
        ]);
    }
}
