<?php

declare(strict_types=1);

namespace Modules\Commerce\Internal\Services;

use Illuminate\Database\UniqueConstraintViolationException;
use Modules\Commerce\Internal\Enums\SaleState;
use Modules\Commerce\Internal\Models\Sale;
use Modules\Commerce\Internal\Models\SaleLine;
use Modules\Commerce\Internal\Models\SyncAnomaly;
use Modules\Commerce\Internal\Sync\OfflineSaleRequest;
use Modules\Commerce\Internal\Sync\OfflineSyncResult;
use Ramsey\Uuid\Uuid;

final class OfflineSyncService
{
    /**
     * @param list<OfflineSaleRequest> $requests
     */
    public function sync(array $requests): OfflineSyncResult
    {
        $processed = 0;
        $replayed  = 0;
        $anomalies = 0;
        $failed    = 0;

        foreach ($requests as $request) {
            $existing = Sale::where('idempotency_key', $request->idempotencyKey)->first();

            if ($existing !== null) {
                $replayed++;
                continue;
            }

            $sale = null;

            try {
                $sale = Sale::create([
                    'id'              => Uuid::uuid7()->toString(),
                    'cash_session_id' => $request->cashSessionId,
                    'state'           => SaleState::Draft,
                    'idempotency_key' => $request->idempotencyKey,
                ]);

                foreach ($request->lines as $line) {
                    SaleLine::create([
                        'sale_id'                  => $sale->id,
                        'product_id'               => $line['product_id'],
                        'designation'              => $line['designation'],
                        'unit_price'               => $line['unit_price'],
                        'vat_rate'                 => $line['vat_rate'],
                        'quantity'                 => $line['quantity'],
                        'line_total_excluding_tax' => $line['line_total_excluding_tax'],
                        'line_total_tax'           => $line['line_total_tax'],
                        'line_total_including_tax' => $line['line_total_including_tax'],
                    ]);
                }

                (new SaleConfirmationService())->confirm($sale);

                $anomalies += SyncAnomaly::where('sale_id', $sale->id)->count();
                $processed++;
            } catch (UniqueConstraintViolationException) {
                $replayed++;
            } catch (\Throwable) {
                $sale?->delete();
                $failed++;
            }
        }

        return new OfflineSyncResult($processed, $replayed, $anomalies, $failed);
    }
}
