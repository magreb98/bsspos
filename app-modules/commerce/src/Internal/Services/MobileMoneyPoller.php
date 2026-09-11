<?php

declare(strict_types=1);

namespace Modules\Commerce\Internal\Services;

use Modules\Commerce\Internal\Enums\PaymentStatus;
use Modules\Commerce\Internal\Gateways\MobileMoneyGatewayClient;
use Modules\Commerce\Internal\Models\Payment;

final class MobileMoneyPoller
{
    public function __construct(private readonly MobileMoneyGatewayClient $gateway)
    {
    }

    public function poll(): int
    {
        $stale = Payment::where('status', PaymentStatus::Pending)
            ->where('created_at', '<=', now()->subMinutes(2))
            ->get();

        $processed = 0;

        foreach ($stale as $payment) {
            $status    = $this->gateway->queryStatus((string) $payment->reference);
            $isSuccess = $status === 'success';
            $isFinal   = $isSuccess || $status === 'failed';

            if (! $isFinal) {
                continue;
            }

            $payment->update([
                'status'       => $isSuccess ? PaymentStatus::Confirmed : PaymentStatus::Failed,
                'confirmed_at' => $isSuccess ? now() : null,
            ]);

            $processed++;
        }

        return $processed;
    }
}
