<?php

declare(strict_types=1);

namespace Modules\Commerce\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Commerce\Internal\Services\OfflineSyncService;
use Modules\Commerce\Internal\Sync\OfflineSaleRequest;

final class SyncController
{
    public function __construct(private readonly OfflineSyncService $service)
    {
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'sales'                                        => 'required|array',
            'sales.*.idempotency_key'                      => 'required|string',
            'sales.*.cash_session_id'                      => 'required|uuid',
            'sales.*.lines'                                => 'required|array|min:1',
            'sales.*.lines.*.product_id'                   => 'required|uuid',
            'sales.*.lines.*.quantity'                     => 'required|integer|min:1',
            'sales.*.lines.*.designation'                  => 'required|string',
            'sales.*.lines.*.unit_price'                   => 'required|integer|min:0',
            'sales.*.lines.*.vat_rate'                     => 'required|string',
            'sales.*.lines.*.line_total_excluding_tax'     => 'required|integer|min:0',
            'sales.*.lines.*.line_total_tax'               => 'required|integer|min:0',
            'sales.*.lines.*.line_total_including_tax'     => 'required|integer|min:0',
        ]);

        /** @var list<OfflineSaleRequest> $requests */
        $requests = array_map(
            static fn (array $sale): OfflineSaleRequest => new OfflineSaleRequest(
                idempotencyKey: $sale['idempotency_key'],
                cashSessionId:  $sale['cash_session_id'],
                lines:          $sale['lines'],
            ),
            $data['sales'],
        );

        $result = $this->service->sync($requests);

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
