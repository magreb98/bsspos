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
        $messages = DB::table('outbox_messages')
            ->whereNull('processed_at')
            ->get();

        foreach ($messages as $message) {
            DB::table('outbox_messages')
                ->where('id', $message->id)
                ->update(['processed_at' => now()]);
        }
    }
}
