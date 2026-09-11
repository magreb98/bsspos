<?php

declare(strict_types=1);

use App\Control\Domaine;
use App\Control\Tenant;
use App\Platform\Tenancy\Bootstrappers\SwitchTenantConnection;
use Stancl\Tenancy\TenantDatabaseManagers\MySQLDatabaseManager;
use Stancl\Tenancy\TenantDatabaseManagers\SQLiteDatabaseManager;

return [
    'tenant_model' => Tenant::class,
    'id_generator' => Stancl\Tenancy\UUIDGenerator::class,

    'domain_model' => Domaine::class,

    /**
     * The list of domains hosting your central app.
     *
     * Only relevant if you're using the domain or subdomain identification middleware.
     */
    'central_domains' => explode(',', (string) env('TENANCY_CENTRAL_DOMAINS', '127.0.0.1,localhost')),

    /**
     * Tenancy bootstrappers are executed when tenancy is initialized.
     * Their responsibility is making Laravel features tenant-aware.
     */
    'bootstrappers' => [
        SwitchTenantConnection::class,
        \App\Platform\Identity\Bootstrappers\PrefixPermissionsCache::class,
    ],

    /**
     * Database tenancy config. Used by DatabaseTenancyBootstrapper.
     */
    'database' => [
        'central_connection' => env('DB_CONNECTION', 'sqlite'),

        /**
         * Connection used as a "template" for the dynamically created tenant database connection.
         * Note: don't name your template connection tenant. That name is reserved by package.
         */
        'template_tenant_connection' => env('TENANCY_DB_CONNECTION', 'mysql'),

        /**
         * Tenant database names are created like this:
         * prefix + tenant_id + suffix.
         *
         * Convention : bsspos_tenant_{uuid}
         */
        'prefix' => env('TENANCY_DB_PREFIX', 'bsspos_tenant_'),
        'suffix' => env('TENANCY_DB_SUFFIX', ''),

        /**
         * TenantDatabaseManagers are classes that handle the creation & deletion of tenant databases.
         */
        'managers' => [
            'sqlite' => SQLiteDatabaseManager::class,
            'mysql'  => MySQLDatabaseManager::class,
        ],
    ],

    /**
     * Cache tenancy config. Used by CacheTenancyBootstrapper.
     */
    'cache' => [
        'tag_base' => 'tenant',
    ],

    /**
     * Filesystem tenancy config. Used by FilesystemTenancyBootstrapper.
     */
    'filesystem' => [
        'suffix_base' => 'tenant',
        'disks'       => [
            'local',
            'public',
        ],
        'root_override' => [
            'local'  => '%storage_path%/app/',
            'public' => '%storage_path%/app/public/',
        ],
        'suffix_storage_path' => true,
        'asset_helper_tenancy' => true,
    ],

    /**
     * Redis tenancy config. Used by RedisTenancyBootstrapper.
     */
    'redis' => [
        'prefix_base'         => 'tenant',
        'prefixed_connections' => [],
    ],

    /**
     * Features are classes that provide additional functionality.
     */
    'features' => [],

    /**
     * Should tenancy routes be registered.
     */
    'routes' => true,

    /**
     * Parameters used by the tenants:migrate command.
     */
    'migration_parameters' => [
        '--force'    => true,
        '--path'     => [
            database_path('migrations/tenant'),
            base_path('app-modules/commerce/database/migrations/tenant'),
            base_path('app-modules/secteur-electronique/database/migrations/tenant'),
        ],
        '--realpath' => true,
    ],

    /**
     * Parameters used by the tenants:seed command.
     */
    'seeder_parameters' => [
        '--class' => 'DatabaseSeeder',
    ],
];
