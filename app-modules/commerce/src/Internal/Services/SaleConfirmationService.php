<?php

declare(strict_types=1);

namespace Modules\Commerce\Internal\Services;

use App\Platform\Outbox\Actions\PublishMessage;
use App\Platform\Sequencing\Actions\AllocateNumber;
use Modules\Commerce\Internal\Enums\Granularity;
use Modules\Commerce\Internal\Enums\SaleState;
use Modules\Commerce\Internal\Models\Customer;
use Modules\Commerce\Internal\Models\Sale;
use Modules\Commerce\Internal\Models\StockMovement;
use Modules\Commerce\Internal\Models\SyncAnomaly;
use Ramsey\Uuid\Uuid;

final class SaleConfirmationService
{
    public function confirm(Sale $sale): void
    {
        if ($sale->isConfirmed()) {
            throw new \DomainException('Sale is already confirmed.');
        }

        $session  = $sale->cashSession ?? throw new \DomainException('Sale has no session.');
        $register = $session->cashRegister ?? throw new \DomainException('Session has no register.');
        $pos      = $register->pointOfSale ?? throw new \DomainException('Register has no point of sale.');

        if (! $session->isOpen()) {
            throw new \DomainException('Cannot confirm a sale in a closed session.');
        }

        $lines = $sale->lines()->with('product')->get();

        $totalHt  = $lines->sum(fn ($l) => $l->line_total_excluding_tax?->toInt() ?? 0);
        $totalTax = $lines->sum(fn ($l) => $l->line_total_tax?->toInt() ?? 0);
        $totalTtc = $lines->sum(fn ($l) => $l->line_total_including_tax?->toInt() ?? 0);

        // Must be called from within the caller's DB::transaction().
        $allocator = new AllocateNumber();
        $posUuid   = Uuid::fromString($pos->id);
        $year      = now()->year;

        $allocator->initializeIfAbsent($posUuid, 'sale', $year);
        $sequence = $allocator->allocate($posUuid, 'sale', $year);
        $number   = sprintf('V-%d-%04d', $year, $sequence);

        $sale->update([
            'state'               => SaleState::Confirmed,
            'number'              => $number,
            'total_excluding_tax' => $totalHt,
            'total_tax'           => $totalTax,
            'total_including_tax' => $totalTtc,
            'confirmed_at'        => now(),
        ]);

        foreach ($lines as $line) {
            if ($line->product === null || $line->product->granularity === Granularity::Service) {
                continue;
            }

            // Pessimistic lock prevents concurrent overselling: serialize writers on this product+pos
            StockMovement::where('product_id', $line->product_id)
                ->where('point_of_sale_id', $pos->id)
                ->lockForUpdate()
                ->sum('quantity');

            StockMovement::create([
                'point_of_sale_id' => $pos->id,
                'product_id'       => $line->product_id,
                'sale_line_id'     => $line->id,
                'quantity'         => -$line->quantity,
                'occurred_at'      => now(),
            ]);

            $netStock = (int) StockMovement::where('product_id', $line->product_id)
                ->where('point_of_sale_id', $pos->id)
                ->sum('quantity');

            if ($netStock < 0) {
                SyncAnomaly::create([
                    'sale_id'    => $sale->id,
                    'product_id' => $line->product_id,
                    'type'       => 'stock_conflict',
                    'detail'     => "Stock went negative after sale confirmation.",
                ]);
            }
        }

        // Award loyalty points: 1 point per 1 000 XAF of TTC total
        if ($sale->client_id !== null) {
            $pointsEarned = (int) floor($totalTtc / 1000);

            if ($pointsEarned > 0) {
                Customer::where('id', $sale->client_id)
                    ->lockForUpdate()
                    ->first()
                    ?->increment('loyalty_points', $pointsEarned);
            }
        }

        (new PublishMessage())->publish('commerce.sale.confirmed', [
            'sale_id'             => $sale->id,
            'number'              => $number,
            'total_including_tax' => $totalTtc,
            'confirmed_at'        => now()->toIso8601String(),
        ]);
    }
}
