<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Control\Tenant;
use App\Http\Controllers\Admin\Concerns\RequiresSuperAdmin;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

final class ImpersonationController
{
    use RequiresSuperAdmin;

    /**
     * Generate a short-lived Sanctum token for the tenant's first active proprietaire.
     * The token expires after 2 h and is stored only in the tenant's own DB.
     */
    public function impersonate(Request $request, string $id): JsonResponse
    {
        $this->assertActingAdminIsSuperAdmin($request);

        $tenant = Tenant::find($id);

        if ($tenant === null) {
            return response()->json(['code' => 'NOT_FOUND', 'message' => 'Entreprise introuvable.'], 404);
        }

        if ($tenant->status !== 'actif') {
            return response()->json([
                'code'    => 'TENANT_INACTIVE',
                'message' => 'Cette entreprise n\'est pas active.',
            ], 422);
        }

        $rawToken = null;
        $targetUser = null;

        try {
            $tenant->run(function () use (&$rawToken, &$targetUser): void {
                $user = DB::table('members')
                    ->join('model_has_roles', function ($join): void {
                        $join->on('members.id', '=', 'model_has_roles.model_id')
                            ->where('model_has_roles.model_type', 'App\Platform\Identity\Models\User');
                    })
                    ->join('roles', 'model_has_roles.role_id', '=', 'roles.id')
                    ->where('roles.name', 'proprietaire')
                    ->where('members.active', true)
                    ->select('members.id', 'members.first_name', 'members.last_name', 'members.email')
                    ->first();

                if ($user === null) {
                    return;
                }

                $targetUser = $user;
                $rawToken   = Str::random(64);
                $expiresAt  = now()->addHours(2)->toDateTimeString();

                DB::table('personal_access_tokens')->insert([
                    'id'             => (string) Str::uuid(),
                    'tokenable_type' => 'App\Platform\Identity\Models\User',
                    'tokenable_id'   => $user->id,
                    'name'           => 'admin-impersonation',
                    'token'          => hash('sha256', $rawToken),
                    'abilities'      => '["*"]',
                    'expires_at'     => $expiresAt,
                    'created_at'     => now()->toDateTimeString(),
                    'updated_at'     => now()->toDateTimeString(),
                ]);
            });
        } catch (Throwable $e) {
            return response()->json([
                'code'    => 'TENANT_DB_ERROR',
                'message' => 'Impossible d\'accéder à la base de données du tenant.',
            ], 500);
        }

        if ($rawToken === null || $targetUser === null) {
            return response()->json([
                'code'    => 'NO_PROPRIETAIRE',
                'message' => 'Aucun propriétaire actif trouvé pour ce tenant.',
            ], 422);
        }

        return response()->json([
            'data' => [
                'token'      => $rawToken,
                'expires_at' => now()->addHours(2)->toIso8601String(),
                'user'       => [
                    'id'    => $targetUser->id,
                    'name'  => $targetUser->first_name . ' ' . $targetUser->last_name,
                    'email' => $targetUser->email,
                ],
                'tenant' => [
                    'id'   => $id,
                    'name' => $tenant->name,
                ],
            ],
        ]);
    }
}
