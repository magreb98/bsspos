<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

final class SocleDetectDrift extends Command
{
    protected $signature = 'socle:detect-drift';

    protected $description = 'Lists tenants whose schema is behind the current version';

    public function handle(): int
    {
        $batchMax = (int) (DB::table('migrations')->max('batch') ?? 0);

        $drifted = DB::table('tenant_schema_versions')
            ->get()
            ->filter(fn ($row) => (int) str_replace('batch_', '', $row->version) < $batchMax)
            ->pluck('tenant_id');

        if ($drifted->isEmpty()) {
            $this->info('No drifted tenants.');

            return self::SUCCESS;
        }

        $this->error('Drifted tenants: ' . $drifted->join(', '));

        return self::FAILURE;
    }
}
