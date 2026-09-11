<?php

declare(strict_types=1);

namespace App\Platform\Sequencing\Actions;

use App\Platform\Sequencing\Exceptions\CounterNotFound;
use Illuminate\Support\Facades\DB;
use Ramsey\Uuid\UuidInterface;

final class AllocateNumber
{
    public function initializeIfAbsent(UuidInterface $unitId, string $key, int $fiscalYear): void
    {
        DB::table('sequence')->insertOrIgnore([
            'unit_id'     => $unitId->toString(),
            'key'         => $key,
            'fiscal_year' => $fiscalYear,
            'last_number' => 0,
        ]);
    }

    /**
     * Allocates the next sequential number with no gaps.
     * Must be called within the caller's transaction.
     *
     * @throws CounterNotFound if the counter row does not exist yet
     */
    public function allocate(UuidInterface $unitId, string $key, int $fiscalYear): int
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'sqlite') {
            // SQLite does not support SELECT ... FOR UPDATE.
            // Atomicity is guaranteed by SQLite's write lock on the connection.
            $affected = DB::table('sequence')
                ->where('unit_id', $unitId->toString())
                ->where('key', $key)
                ->where('fiscal_year', $fiscalYear)
                ->update(['last_number' => DB::raw('last_number + 1')]);

            if ($affected === 0) {
                throw new CounterNotFound(
                    "No counter for unit {$unitId}, key '{$key}', fiscal year {$fiscalYear}."
                );
            }

            $row = DB::table('sequence')
                ->where('unit_id', $unitId->toString())
                ->where('key', $key)
                ->where('fiscal_year', $fiscalYear)
                ->first();

            if ($row === null) {
                throw new CounterNotFound(
                    "No counter for unit {$unitId}, key '{$key}', fiscal year {$fiscalYear}."
                );
            }

            return (int) $row->last_number;
        }

        // PostgreSQL: SELECT ... FOR UPDATE to prevent race conditions
        $row = DB::table('sequence')
            ->where('unit_id', $unitId->toString())
            ->where('key', $key)
            ->where('fiscal_year', $fiscalYear)
            ->lockForUpdate()
            ->first();

        if ($row === null) {
            throw new CounterNotFound(
                "No counter for unit {$unitId}, key '{$key}', fiscal year {$fiscalYear}."
            );
        }

        $next = (int) $row->last_number + 1;

        DB::table('sequence')
            ->where('unit_id', $unitId->toString())
            ->where('key', $key)
            ->where('fiscal_year', $fiscalYear)
            ->update(['last_number' => $next]);

        return $next;
    }
}
