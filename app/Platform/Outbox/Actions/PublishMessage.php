<?php

declare(strict_types=1);

namespace App\Platform\Outbox\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class PublishMessage
{
    /**
     * Inserts a message into outbox_messages within the active transaction.
     *
     * @param  array<string, mixed>  $payload
     */
    public function publish(string $type, array $payload): void
    {
        DB::table('outbox_messages')->insert([
            'id'           => Str::uuid()->toString(),
            'type'         => $type,
            'payload'      => json_encode($payload),
            'created_at'   => now(),
            'processed_at' => null,
            'attempts'     => 0,
        ]);
    }
}
