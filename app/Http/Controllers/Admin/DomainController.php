<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Control\Domaine;
use App\Control\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class DomainController
{
    public function store(Request $request, string $tenantId): JsonResponse
    {
        $tenant = Tenant::find($tenantId);

        if ($tenant === null) {
            return response()->json(['code' => 'NOT_FOUND', 'message' => 'Entreprise introuvable.'], 404);
        }

        $validated = $request->validate([
            'domain' => ['required', 'string', 'max:255'],
        ]);

        if (Domaine::where('domain', $validated['domain'])->exists()) {
            return response()->json([
                'code'    => 'DOMAIN_ALREADY_EXISTS',
                'message' => 'Ce domaine est déjà utilisé.',
                'champ'   => 'domain',
            ], 422);
        }

        $domain = $tenant->domains()->create(['domain' => $validated['domain']]);

        return response()->json(['data' => $domain->toArray()], 201);
    }

    public function destroy(string $tenantId, string $domainId): JsonResponse
    {
        $domain = Domaine::where('tenant_id', $tenantId)->where('id', $domainId)->first();

        if ($domain === null) {
            return response()->json(['code' => 'NOT_FOUND', 'message' => 'Domaine introuvable.'], 404);
        }

        $remaining = Domaine::where('tenant_id', $tenantId)->count();
        if ($remaining <= 1) {
            return response()->json([
                'code'    => 'LAST_DOMAIN',
                'message' => 'Impossible de supprimer le dernier domaine d\'une entreprise.',
            ], 422);
        }

        $domain->delete();

        return response()->json([], 204);
    }
}
