<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Concerns;

use App\Control\AdminUser;
use Illuminate\Http\Request;

/**
 * Gates the highest-blast-radius admin actions (managing other admin
 * accounts, destroying a tenant) behind is_super_admin, so a single
 * compromised regular-admin token can't escalate to full platform control.
 */
trait RequiresSuperAdmin
{
    private function assertActingAdminIsSuperAdmin(Request $request): void
    {
        $actingAdmin = $request->attributes->get('admin_user');

        if (! $actingAdmin instanceof AdminUser || ! $actingAdmin->isSuperAdmin()) {
            abort(response()->json([
                'code'    => 'FORBIDDEN',
                'message' => 'Réservé aux super-administrateurs.',
            ], 403));
        }
    }
}
