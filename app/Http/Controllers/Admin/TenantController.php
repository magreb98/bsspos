<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Control\Domaine;
use App\Control\Tenant;
use App\Http\Controllers\Admin\Concerns\RequiresSuperAdmin;
use App\Http\Requests\Admin\StoreTenantRequest;
use App\Http\Requests\Admin\UpdateTenantRequest;
use App\Http\Resources\Admin\TenantMemberResource;
use App\Http\Resources\Admin\TenantResource;
use App\Platform\Tenancy\Jobs\ProvisionTenantJob;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class TenantController
{
    use RequiresSuperAdmin;

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
            'data' => TenantResource::collection($paginator->items()),
            'meta' => [
                'total'        => $paginator->total(),
                'current_page' => $paginator->currentPage(),
                'per_page'     => $paginator->perPage(),
                'last_page'    => $paginator->lastPage(),
            ],
        ]);
    }

    public function store(StoreTenantRequest $request): JsonResponse
    {
        $validated = $request->validated();

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

        return response()->json(['data' => new TenantResource($tenant)], 201);
    }

    public function show(string $id): JsonResponse
    {
        $tenant = Tenant::with('domains')->find($id);

        if ($tenant === null) {
            return response()->json(['code' => 'NOT_FOUND', 'message' => 'Entreprise introuvable.'], 404);
        }

        return response()->json(['data' => new TenantResource($tenant)]);
    }

    public function update(UpdateTenantRequest $request, string $id): JsonResponse
    {
        $tenant = Tenant::find($id);

        if ($tenant === null) {
            return response()->json(['code' => 'NOT_FOUND', 'message' => 'Entreprise introuvable.'], 404);
        }

        $validated = $request->validated();

        $tenant->update($validated);

        $tenant->load('domains');

        return response()->json(['data' => new TenantResource($tenant)]);
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

        return response()->json(['data' => new TenantResource($tenant)]);
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

        return response()->json(['data' => TenantMemberResource::collection($users)]);
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $this->assertActingAdminIsSuperAdmin($request);

        $tenant = Tenant::find($id);

        if ($tenant === null) {
            return response()->json(['code' => 'NOT_FOUND', 'message' => 'Entreprise introuvable.'], 404);
        }

        $tenant->update(['status' => 'archive']);

        return response()->json([], 204);
    }
}
