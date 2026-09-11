<?php

declare(strict_types=1);

namespace Modules\Commerce\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Commerce\Internal\Models\PaymentMethodConfig;

final class PaymentMethodController
{
    public function index(): JsonResponse
    {
        $methods = PaymentMethodConfig::orderBy('label')->get();

        return response()->json(['data' => $methods->toArray()]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'key'          => ['required', 'string', 'max:50', 'unique:payment_methods,key'],
            'label'        => ['required', 'string', 'max:100'],
            'auto_confirm' => ['sometimes', 'boolean'],
        ]);

        $method = PaymentMethodConfig::create([
            'key'          => strtolower($validated['key']),
            'label'        => $validated['label'],
            'auto_confirm' => $validated['auto_confirm'] ?? false,
            'active'       => true,
        ]);

        return response()->json(['data' => $method->toArray()], 201);
    }

    public function update(Request $request, PaymentMethodConfig $paymentMethod): JsonResponse
    {
        if (in_array($paymentMethod->key, ['cash', 'mobile_money'], true)) {
            return response()->json(['code' => 'METHOD_LOCKED', 'message' => 'Les méthodes de paiement par défaut ne peuvent pas être modifiées.', 'champ' => null], 409);
        }

        $validated = $request->validate([
            'label'        => ['sometimes', 'string', 'max:100'],
            'auto_confirm' => ['sometimes', 'boolean'],
            'active'       => ['sometimes', 'boolean'],
        ]);

        $paymentMethod->update($validated);

        return response()->json(['data' => $paymentMethod->fresh()?->toArray() ?? $paymentMethod->toArray()]);
    }
}
