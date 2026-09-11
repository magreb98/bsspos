<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Ensures model_id on model_has_roles and model_has_permissions is char(36)
 * so that UUID primary keys can be stored without data truncation.
 *
 * The permission migration publishes model_id as unsignedBigInteger by default.
 * This migration corrects the column type using raw DDL (worker-restart-proof).
 */
return new class () extends Migration {
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        $tables = [
            'model_has_roles' => [
                'fk'   => 'model_has_roles_role_id_foreign',
                'fk_col' => 'role_id',
                'ref'  => 'roles',
                'pk'   => ['role_id', 'model_id', 'model_type'],
            ],
            'model_has_permissions' => [
                'fk'   => 'model_has_permissions_permission_id_foreign',
                'fk_col' => 'permission_id',
                'ref'  => 'permissions',
                'pk'   => ['permission_id', 'model_id', 'model_type'],
            ],
        ];

        foreach ($tables as $table => $info) {
            $col = DB::selectOne(
                "SELECT COLUMN_TYPE FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME   = ?
                   AND COLUMN_NAME  = 'model_id'",
                [$table]
            );

            // Already char(36) — nothing to do.
            if ($col && strtolower((string) $col->COLUMN_TYPE) === 'char(36)') {
                continue;
            }

            $pk = implode('`, `', $info['pk']);

            // MySQL won't allow DROP + ADD of the same FK name in one statement.
            // Split into two: first drop constraints, then re-add them.
            DB::statement("
                ALTER TABLE `{$table}`
                    DROP FOREIGN KEY `{$info['fk']}`,
                    DROP PRIMARY KEY,
                    MODIFY `model_id` char(36) NOT NULL
            ");

            DB::statement("
                ALTER TABLE `{$table}`
                    ADD PRIMARY KEY (`{$pk}`),
                    ADD CONSTRAINT `{$info['fk']}`
                        FOREIGN KEY (`{$info['fk_col']}`)
                        REFERENCES `{$info['ref']}` (`id`)
                        ON DELETE CASCADE
            ");
        }
    }

    public function down(): void
    {
        // Intentionally not reversing: bigint was wrong, reverting would break things.
    }
};
