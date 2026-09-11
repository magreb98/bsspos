<?php

declare(strict_types=1);

namespace Modules\Commerce\Internal\Services;

use Modules\Commerce\Internal\Enums\PaymentStatus;
use Modules\Commerce\Internal\Models\Payment;

final class MobileMoneyCallbackHandler
{
    public function __construct(private readonly string $secret)
    {
    }

    public function handle(
        string $reference,
        string $status,
        string $rawBody,
        string $signature,
    ): void {
        $expected = 'sha256=' . hash_hmac('sha256', $rawBody, $this->secret);

        if (! hash_equals($expected, $signature)) {
            throw new \InvalidArgumentException('Invalid mobile money callback signature.');
        }

        $payment = Payment::where('reference', $reference)->first();

        if ($payment === null) {
            return;
        }

        if ($payment->status !== PaymentStatus::Pending) {
            return;
        }

        $isSuccess = $status === 'success';

        $payment->update([
            'status'       => $isSuccess ? PaymentStatus::Confirmed : PaymentStatus::Failed,
            'confirmed_at' => $isSuccess ? now() : null,
        ]);
    }
}
