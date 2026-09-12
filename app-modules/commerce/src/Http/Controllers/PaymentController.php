<?php

declare(strict_types=1);

namespace Modules\Commerce\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Modules\Commerce\Http\Requests\StorePaymentRequest;
use Modules\Commerce\Http\Resources\PaymentResource;
use Modules\Commerce\Internal\Enums\PaymentStatus;
use Modules\Commerce\Internal\Models\Currency;
use Modules\Commerce\Internal\Models\Payment;
use Modules\Commerce\Internal\Models\PaymentMethodConfig;
use Modules\Commerce\Internal\Models\Sale;
use Modules\Commerce\Internal\Services\SaleAccessGuard;

final class PaymentController
{
    public function __construct(
        private readonly SaleAccessGuard $accessGuard,
    ) {
    }

    public function index(Sale $sale): JsonResponse
    {
        $this->accessGuard->ensureAccessible($sale);

        return response()->json(['data' => PaymentResource::collection($sale->payments()->orderBy('created_at')->get())]);
    }

    public function store(StorePaymentRequest $request, Sale $sale): JsonResponse
    {
        $this->accessGuard->ensureAccessible($sale);

        $validated = $request->validated();

        $currencyCode = strtoupper((string) ($validated['currency_code'] ?? 'XAF'));
        $amountInput  = (int) $validated['amount'];

        if ($currencyCode === 'XAF') {
            $exchangeRate    = 1000;
            $amountXaf       = $amountInput;
            $amountOriginal  = null;
        } else {
            $currency = Currency::where('code', $currencyCode)->where('active', true)->first();

            if ($currency === null) {
                return response()->json(['code' => 'CURRENCY_NOT_FOUND', 'message' => 'Currency not found or inactive.', 'champ' => 'currency_code'], 422);
            }

            $exchangeRate   = isset($validated['exchange_rate']) ? (int) $validated['exchange_rate'] : $currency->exchange_rate;
            $amountXaf      = intdiv($amountInput * $exchangeRate + 500, 1000);
            $amountOriginal = $amountInput;
        }

        $methodConfig = PaymentMethodConfig::where('key', $validated['method'])->first();
        $autoConfirm  = $methodConfig?->auto_confirm === true;

        $payment = Payment::create([
            'sale_id'         => $sale->id,
            'method'          => $validated['method'],
            'amount'          => $amountXaf,
            'currency_code'   => $currencyCode,
            'exchange_rate'   => $exchangeRate,
            'amount_original' => $amountOriginal,
            'reference'       => $validated['reference'] ?? null,
            'status'          => $autoConfirm ? PaymentStatus::Confirmed : PaymentStatus::Pending,
            'confirmed_at'    => $autoConfirm ? now() : null,
        ]);

        return response()->json(['data' => new PaymentResource($payment)], 201);
    }
}
