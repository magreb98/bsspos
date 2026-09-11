<?php

declare(strict_types=1);

namespace Modules\Commerce\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Commerce\Internal\Enums\PaymentStatus;
use Modules\Commerce\Internal\Models\Currency;
use Modules\Commerce\Internal\Models\Payment;
use Modules\Commerce\Internal\Models\PaymentMethodConfig;
use Modules\Commerce\Internal\Models\Sale;

final class PaymentController
{
    public function index(Sale $sale): JsonResponse
    {
        return response()->json(['data' => $sale->payments()->orderBy('created_at')->get()->toArray()]);
    }

    public function store(Request $request, Sale $sale): JsonResponse
    {
        $activeKeys = PaymentMethodConfig::where('active', true)->pluck('key')->toArray();

        $validated = $request->validate([
            'method'        => ['required', 'string', 'in:' . implode(',', $activeKeys)],
            'amount'        => ['required', 'integer', 'min:1'],
            'reference'     => ['sometimes', 'nullable', 'string', 'max:100'],
            'currency_code' => ['sometimes', 'string', 'size:3'],
            'exchange_rate' => ['sometimes', 'integer', 'min:1'],
        ]);

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

        return response()->json(['data' => $payment->toArray()], 201);
    }
}
