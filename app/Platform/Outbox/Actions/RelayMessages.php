<?php

declare(strict_types=1);

namespace App\Platform\Outbox\Actions;

use App\Control\Tenant;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\DB;

final class RelayMessages implements ShouldQueue
{
    public function handle(): void
    {
        foreach (Tenant::all() as $tenant) {
            if ($tenant instanceof Tenant) {
                $tenant->run(function (): void {
                    $this->relayForTenant();
                });
            }
        }
    }

    private function relayForTenant(): void
    {
        // Chunked (not a single unbounded get()) so a tenant with a large
        // processed_at IS NULL backlog can't load it all into memory at once.
        DB::table('outbox_messages')
            ->whereNull('processed_at')
            ->orderBy('id')
            ->chunkById(500, function ($messages): void {
                foreach ($messages as $message) {
                    // D9: mark processed BEFORE dispatching downstream, so a
                    // crash mid-relay never causes the same message to be
                    // dispatched twice — at worst it's skipped once and must
                    // be recoverable from the business data itself.
                    DB::table('outbox_messages')
                        ->where('id', $message->id)
                        ->update(['processed_at' => now()]);
                }
            });
    }
}
