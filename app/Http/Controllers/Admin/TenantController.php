<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Control\Domaine;
use App\Control\Tenant;
use App\Platform\Tenancy\Jobs\ProvisionTenantJob;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class TenantController
{
    public function index(Request $request): JsonResponse
    {
        $sortable = ['created_at', 'name', 'status', 'provisioning_step'];
        $sortBy   = in_array($request->input('sort'), $sortable, true) ? $request->input('sort') : 'created_at';
        $order    = $request->input('order', 'desc') === 'asc' ? 'asc' : 'desc';

        $query = Tenant::with('domains')->orderBy($sortBy, $order);

        if ($request->filled('search')) {
            $search = $request->string('search')->toString();
            $query->where(function ($q) use ($search): void {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhereHas('domains', fn ($d) => $d->where('domain', 'like', "%{$search}%"));
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $perPage   = min((int) $request->input('per_page', 15), 100);
        $paginator = $query->paginate($perPage);

        return response()->json([
            'data' => $paginator->items(),
            'meta' => [
                'total'        => $paginator->total(),
                'current_page' => $paginator->currentPage(),
                'per_page'     => $paginator->perPage(),
                'last_page'    => $paginator->lastPage(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'                       => ['required', 'string', 'max:255'],
            'domain'                     => ['required', 'string', 'max:255'],
            'initial_admin'              => ['required', 'array'],
            'initial_admin.first_name'   => ['required', 'string'],
            'initial_admin.last_name'    => ['required', 'string'],
            'initial_admin.phone'        => ['required', 'string'],
            'initial_admin.password'     => ['required', 'string', 'min:8'],
        ]);

        if (Domaine::where('domain', $validated['domain'])->exists()) {
            return response()->json([
                'code'    => 'DOMAIN_ALREADY_EXISTS',
                'message' => 'Ce domaine est déjà utilisé par une autre entreprise.',
                'champ'   => 'domain',
            ], 422);
        }

        $tenant = Tenant::create([
            'name'               => $validated['name'],
            'status'             => 'provisionning',
            'provisioning_step'  => 0,
            'provisioning_error' => null,
            'data'               => ['initial_admin' => $validated['initial_admin']],
        ]);

        $tenant->domains()->create(['domain' => $validated['domain']]);

        ProvisionTenantJob::dispatch($tenant);

        $tenant->load('domains');

        return response()->json(['data' => $tenant->toArray()], 201);
    }

    public function show(string $id): JsonResponse
    {
        $tenant = Tenant::with('domains')->find($id);

        if ($tenant === null) {
            return response()->json(['code' => 'NOT_FOUND', 'message' => 'Entreprise introuvable.'], 404);
        }

        return response()->json(['data' => $tenant->toArray()]);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $tenant = Tenant::find($id);

        if ($tenant === null) {
            return response()->json(['code' => 'NOT_FOUND', 'message' => 'Entreprise introuvable.'], 404);
        }

        $validated = $request->validate([
            'name'   => ['sometimes', 'string', 'max:255'],
            'status' => ['sometimes', 'string', 'in:actif,suspendu'],
        ]);

        $tenant->update($validated);

        $tenant->load('domains');

        return response()->json(['data' => $tenant->toArray()]);
    }

    public function reprovision(string $id): JsonResponse
    {
        $tenant = Tenant::find($id);

        if ($tenant === null) {
            return response()->json(['code' => 'NOT_FOUND', 'message' => 'Entreprise introuvable.'], 404);
        }

        // Keep provisioning_step as-is: ProvisionTenant::execute() skips already-completed
        // steps (checks `< step`), so resetting to 0 would re-attempt DB creation and fail.
        $tenant->update([
            'provisioning_error' => null,
            'status'             => 'provisionning',
        ]);

        ProvisionTenantJob::dispatch($tenant);

        $tenant->load('domains');

        return response()->json(['data' => $tenant->toArray()]);
    }

    public function users(string $id): JsonResponse
    {
        $tenant = Tenant::find($id);

        if ($tenant === null) {
            return response()->json(['code' => 'NOT_FOUND', 'message' => 'Entreprise introuvable.'], 404);
        }

        $users = [];

        try {
            $tenant->run(function () use (&$users): void {
                $users = \Illuminate\Support\Facades\DB::table('members')
                    ->select('id', 'first_name', 'last_name', 'phone', 'email', 'active', 'last_connected_at', 'created_at')
                    ->orderBy('first_name')
                    ->get()
                    ->toArray();
            });
        } catch (\Throwable) {
            // Tenant DB not ready
        }

        return response()->json(['data' => $users]);
    }

    public function destroy(string $id): JsonResponse
    {
        $tenant = Tenant::find($id);

        if ($tenant === null) {
            return response()->json(['code' => 'NOT_FOUND', 'message' => 'Entreprise introuvable.'], 404);
        }

        $tenant->update(['status' => 'archive']);

        return response()->json([], 204);
    }
}
