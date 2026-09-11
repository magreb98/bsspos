<?php

declare(strict_types=1);

if (! function_exists('seedTenantRolesAndPermissions')) {
    function seedTenantRolesAndPermissions(): void
    {
        (new \App\Platform\Identity\Seeders\RolesAndPermissionsSeeder())->run();
    }
}
