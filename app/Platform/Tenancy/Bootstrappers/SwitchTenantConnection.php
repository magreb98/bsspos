<?php

declare(strict_types=1);

namespace App\Platform\Tenancy\Bootstrappers;

use App\Platform\Registry\ModuleRegistry;
use Illuminate\Database\Connection as BaseConnection;
use Illuminate\Database\DatabaseManager as LaravelDbManager;
use Illuminate\Database\SQLiteConnection;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Artisan;
use Stancl\Tenancy\Contracts\TenancyBootstrapper;
use Stancl\Tenancy\Contracts\Tenant;
use Stancl\Tenancy\Contracts\TenantWithDatabase;
use Stancl\Tenancy\DatabaseConfig;

/**
 * Bootstrapper that switches the database connection to the tenant's database.
 *
 * In test environments (SQLite in-memory), the `tenant` connection reuses
 * the same SQLite PDO to avoid breaking RefreshDatabase transactions.
 * The `database` property of the connection config is updated with the symbolic
 * name `bsspos_tenant_{uuid}` so that `getDatabaseName()` returns the value
 * expected by tests, without attempting a real PostgreSQL connection.
 *
 * In production (PostgreSQL), the `tenant` connection is a real pgsql connection.
 */
final class SwitchTenantConnection implements TenancyBootstrapper
{
    private string $centralConnection;

    public function __construct(
        private readonly LaravelDbManager $db,
    ) {
        $this->centralConnection = (string) config('tenancy.database.central_connection', 'sqlite');
    }

    /**
     * Switches the connection to the tenant's database.
     */
    public function bootstrap(Tenant $tenant): void
    {
        /** @var TenantWithDatabase $tenant */
        $tenantDatabaseName = (string) (new DatabaseConfig($tenant))->getName();

        // Purge any existing tenant connection.
        if (array_key_exists('tenant', $this->db->getConnections())) {
            $this->db->purge('tenant');
        }

        if (App::environment('testing')) {
            // In test: create the `tenant` connection with a symbolic SQLite config
            // and the same PDO as the central connection (in-memory).
            // This ensures getDatabaseName() returns 'bsspos_tenant_{uuid}'
            // and RefreshDatabase transactions work on the same PDO.
            $this->bootstrapTest($tenantDatabaseName);
        } else {
            // In production: standard creation via Stancl DatabaseTenancyBootstrapper.
            $this->bootstrapProduction($tenant);
        }

        // Switch the default connection to `tenant`.
        config(['database.default' => 'tenant']);
        $this->db->setDefaultConnection('tenant');

        // In test, apply tenant migrations if the pivot table is missing.
        // Not applied when already inside tenants:migrate (avoids recursion).
        if (App::environment('testing') && ! $this->isRunningTenantsMigrate()) {
            $this->applyTenantMigrationsIfNeeded();
        }
    }

    /**
     * Restores the central connection.
     */
    public function revert(): void
    {
        if (array_key_exists('tenant', $this->db->getConnections())) {
            $this->db->purge('tenant');
        }

        config(['database.connections.tenant' => null]);

        config(['database.default' => $this->centralConnection]);
        $this->db->setDefaultConnection($this->centralConnection);
    }

    /**
     * Test bootstrap: symbolic connection reusing the central SQLite PDO.
     */
    private function bootstrapTest(string $tenantDatabaseName): void
    {
        // Retrieve the central connection (sqlite in-memory).
        $centralConnection = $this->db->connection($this->centralConnection);

        // Tenant connection config with the symbolic name.
        // The 'name' field is required for Connection::getName() to return 'tenant'
        // during migration execution (Migrator::runMethod → setDefaultConnection).
        $tenantConfig = [
            'driver'   => 'sqlite',
            'database' => $tenantDatabaseName,  // symbolic: 'bsspos_tenant_{uuid}'
            'prefix'   => '',
            'name'     => 'tenant',
        ];

        // Register in the Laravel config.
        config(['database.connections.tenant' => $tenantConfig]);

        // Create the connection by reusing the central SQLite PDO (in-memory).
        $pdo = $centralConnection->getPdo();

        // The `tenant` connection shares the in-memory PDO of the central connection.
        $tenantConnection = new SQLiteConnection(
            $pdo,
            $tenantDatabaseName,
            '',
            $tenantConfig,
        );

        // Sync the Laravel transaction counter with the PDO's actual level.
        // RefreshDatabase wraps the central 'sqlite' connection in a transaction
        // (level 1). The shared PDO is already in that transaction. Without this,
        // DB::transaction() in controllers would call exec("BEGIN DEFERRED TRANSACTION")
        // on an already-open PDO transaction and get SQLSTATE HY000 on PHP 8.4+.
        // Setting the counter to 1 makes DB::transaction() use SAVEPOINTs instead.
        $refTx = new \ReflectionProperty(BaseConnection::class, 'transactions');
        $refTx->setValue($tenantConnection, 1);

        // Direct injection into the DatabaseManager's connections array.
        // PHP reflection is required because the `connections` property is protected.
        // setAccessible() is unnecessary since PHP 8.1 — removed.
        $refProp = new \ReflectionProperty($this->db, 'connections');
        $connections = $refProp->getValue($this->db);
        $connections['tenant'] = $tenantConnection;
        $refProp->setValue($this->db, $connections);
    }

    /**
     * Applies tenant migrations on the shared SQLite database if tables are missing.
     * Collects paths from the default tenant folder + all registered module manifests.
     * Needed for tests that call tenancy()->initialize() directly.
     */
    private function applyTenantMigrationsIfNeeded(): void
    {
        if (! $this->db->connection('tenant')->getSchemaBuilder()->hasTable('members')) {
            $paths = [database_path('migrations/tenant')];

            /** @var ModuleRegistry $registry */
            $registry = App::make(ModuleRegistry::class);

            foreach ($registry->all() as $module) {
                foreach ($module->migrations ?? [] as $basePath) {
                    $tenantPath = rtrim($basePath, '/\\') . '/tenant';
                    if (is_dir($tenantPath)) {
                        $paths[] = $tenantPath;
                    }
                }
            }

            Artisan::call('migrate', [
                '--path'     => $paths,
                '--realpath' => true,
                '--force'    => true,
            ]);
        }
    }

    /**
     * Detects whether the bootstrapper is being called from the tenants:migrate command
     * (via the PHP call stack) to avoid double migration in tests.
     */
    private function isRunningTenantsMigrate(): bool
    {
        foreach (debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 20) as $frame) {
            if (
                isset($frame['class'])
                && $frame['class'] === \Stancl\Tenancy\Commands\Migrate::class
            ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Production bootstrap: delegate to standard Stancl logic.
     */
    private function bootstrapProduction(Tenant $tenant): void
    {
        /** @var TenantWithDatabase $tenant */
        $dbConfig = new DatabaseConfig($tenant);
        $tenantConfig = $dbConfig->connection();

        config(['database.connections.tenant' => $tenantConfig]);
    }
}
