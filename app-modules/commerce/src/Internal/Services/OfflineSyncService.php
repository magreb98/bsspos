<?php

declare(strict_types=1);

namespace Modules\Commerce\Internal\Services;

use App\Platform\Identity\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Modules\Commerce\Internal\Enums\SaleState;
use Modules\Commerce\Internal\Models\CashSession;
use Modules\Commerce\Internal\Models\Sale;
use Modules\Commerce\Internal\Models\SaleLine;
use Modules\Commerce\Internal\Models\SyncAnomaly;
use Modules\Commerce\Internal\Sync\OfflineSaleRequest;
use Modules\Commerce\Internal\Sync\OfflineSyncResult;

final class OfflineSyncService
{
    /**
     * @param list<OfflineSaleRequest> $requests
     */
    public function sync(array $requests, ?User $actingMember = null): OfflineSyncResult
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

            // A member may only push offline sales attributed to a cash
            // session they actually opened, unless they hold inventory.write
            // (managers) — otherwise any authenticated member could inflate
            // or falsify another till's figures just by guessing/reusing a
            // cash_session_id that isn't theirs.
            if ($actingMember !== null && ! $actingMember->can('inventory.write')) {
                $ownedByActor = CashSession::whereKey($request->cashSessionId)
                    ->where('opened_by', (string) $actingMember->getAuthIdentifier())
                    ->exists();

                if (! $ownedByActor) {
                    $failed++;
                    continue;
                }
            }

            $sale = null;

            try {
                // Let HasUuids assign the primary key, same as every other
                // Sale::create() call site — 'id' isn't in $fillable, so
                // passing one explicitly threw a MassAssignmentException
                // that this method's broad catch (\Throwable) below was
                // silently swallowing as a "failed" sync.
                $sale = Sale::create([
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
