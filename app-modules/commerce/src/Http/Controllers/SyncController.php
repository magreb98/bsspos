<?php

declare(strict_types=1);

namespace Modules\Commerce\Http\Controllers;

use App\Platform\Identity\Models\User;
use Illuminate\Http\JsonResponse;
use Modules\Commerce\Http\Requests\SyncRequest;
use Modules\Commerce\Internal\Services\OfflineSyncService;
use Modules\Commerce\Internal\Sync\OfflineSaleRequest;

final class SyncController
{
    public function __construct(private readonly OfflineSyncService $service)
    {
    }

    public function store(SyncRequest $request): JsonResponse
    {
        $data = $request->validated();

        /** @var list<OfflineSaleRequest> $requests */
        $requests = array_map(
            static fn (array $sale): OfflineSaleRequest => new OfflineSaleRequest(
                idempotencyKey: $sale['idempotency_key'],
                cashSessionId:  $sale['cash_session_id'],
                lines:          $sale['lines'],
            ),
            $data['sales'],
        );

        /** @var User $member */
        $member = $request->user();

        $result = $this->service->sync($requests, $member);

        return response()->json([
            'data' => [
                'processed' => $result->processed,
                'replayed'  => $result->replayed,
                'anomalies' => $result->anomalies,
                'failed'    => $result->failed,
            ],
        ]);
    }
}
