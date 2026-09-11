<?php

declare(strict_types=1);

namespace App\Platform\Identity\Seeders;

use App\Platform\Identity\Enums\AppPermission;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

final class RolesAndPermissionsSeeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (AppPermission::allValues() as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        $vendeur = Role::firstOrCreate(['name' => 'vendeur',       'guard_name' => 'web']);
        $gerant  = Role::firstOrCreate(['name' => 'gérant',        'guard_name' => 'web']);
        $proprio = Role::firstOrCreate(['name' => 'proprietaire',  'guard_name' => 'web']);

        $vendeur->syncPermissions(AppPermission::vendeurValues());
        $gerant->syncPermissions(AppPermission::gerantValues());
        $proprio->syncPermissions(AppPermission::allValues());

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
