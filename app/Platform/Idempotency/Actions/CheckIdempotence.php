<?php

declare(strict_types=1);

namespace App\Platform\Idempotency\Actions;

use App\Platform\Idempotency\Exceptions\ConflictingRequestException;
use App\Platform\Idempotency\Values\IdempotenceResult;
use Illuminate\Support\Facades\DB;

final class CheckIdempotence
{
    /**
     * @throws ConflictingRequestException if the same key is reused with a different fingerprint
     */
    public function check(string $key, string $fingerprint): IdempotenceResult
    {
        $row = DB::table('idempotency_keys')->where('key', $key)->first();

        if ($row === null) {
            // `key` is the table's primary key: two near-simultaneous requests
            // with the same key could both see "absent" here. insertOrIgnore()
            // silently no-ops on the PK conflict instead of throwing, so the
            // loser doesn't blow up with an unhandled QueryException. We then
            // re-select unconditionally and, if our own insert didn't land
            // (another request won the race), fall through to the normal
            // existing-row handling below instead of assuming we created it.
            $inserted = DB::table('idempotency_keys')->insertOrIgnore([
                'key'                 => $key,
                'request_fingerprint' => $fingerprint,
                'response'            => null,
                'status'              => null,
                'expires_at'          => now()->addDays(30),
            ]);

            $row = DB::table('idempotency_keys')->where('key', $key)->first();

            if ($inserted > 0) {
                return new IdempotenceResult(true, null, null);
            }
        }

        if (now()->isAfter($row->expires_at)) {
            DB::table('idempotency_keys')->where('key', $key)->delete();

            DB::table('idempotency_keys')->insert([
                'key'                 => $key,
                'request_fingerprint' => $fingerprint,
                'response'            => null,
                'status'              => null,
                'expires_at'          => now()->addDays(30),
            ]);

            return new IdempotenceResult(true, null, null);
        }

        if ($row->request_fingerprint !== $fingerprint) {
            throw new ConflictingRequestException(
                "Idempotency key '{$key}' was reused with a different fingerprint."
            );
        }

        $response = $row->response !== null ? json_decode($row->response, true) : null;
        $status   = $row->status !== null ? (int) $row->status : null;

        return new IdempotenceResult(false, $response, $status);
    }

    /**
     * @param  array<string, mixed>  $response
     */
    public function record(string $key, string $fingerprint, array $response, int $status): void
    {
        DB::table('idempotency_keys')
            ->where('key', $key)
            ->update([
                'response' => json_encode($response),
                'status'   => $status,
            ]);
    }
}
