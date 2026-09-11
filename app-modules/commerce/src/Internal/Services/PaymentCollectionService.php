<?php

declare(strict_types=1);

namespace Modules\Commerce\Internal\Services;

use Modules\Commerce\Internal\Enums\PaymentMethod;
use Modules\Commerce\Internal\Enums\PaymentStatus;
use Modules\Commerce\Internal\Enums\SaleState;
use Modules\Commerce\Internal\Models\Payment;
use Modules\Commerce\Internal\Models\Sale;

final class PaymentCollectionService
{
    public function collect(
        Sale $sale,
        PaymentMethod $method,
        int $amount,
        ?string $reference = null,
    ): Payment {
        if ($sale->state !== SaleState::Confirmed) {
            throw new \DomainException('Cannot collect payment on a non-confirmed sale.');
        }

        if ($amount <= 0) {
            throw new \DomainException('Payment amount must be positive.');
        }

        $already = $this->amountCollected($sale);
        $total   = $sale->total_including_tax?->toInt() ?? 0;

        if ($already + $amount > $total) {
            throw new \DomainException(
                "Payment would exceed sale total: collected {$already}, adding {$amount}, total {$total}."
            );
        }

        $isCash       = $method === PaymentMethod::Cash;
        $status       = $isCash ? PaymentStatus::Confirmed : PaymentStatus::Pending;
        $confirmedAt  = $isCash ? now() : null;

        return Payment::create([
            'sale_id'      => $sale->id,
            'method'       => $method,
            'amount'       => $amount,
            'status'       => $status,
            'reference'    => $reference,
            'confirmed_at' => $confirmedAt,
        ]);
    }

    public function amountCollected(Sale $sale): int
    {
        return (int) Payment::where('sale_id', $sale->id)
            ->where('status', PaymentStatus::Confirmed)
            ->sum('amount');
    }

    public function isFullyPaid(Sale $sale): bool
    {
        return $this->amountCollected($sale) >= ($sale->total_including_tax?->toInt() ?? 0);
    }
}
