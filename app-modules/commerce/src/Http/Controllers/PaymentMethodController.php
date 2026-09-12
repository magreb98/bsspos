<?php

declare(strict_types=1);

namespace Modules\Commerce\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Modules\Commerce\Http\Requests\StorePaymentMethodRequest;
use Modules\Commerce\Http\Requests\UpdatePaymentMethodRequest;
use Modules\Commerce\Http\Resources\PaymentMethodConfigResource;
use Modules\Commerce\Internal\Models\PaymentMethodConfig;

final class PaymentMethodController
{
    public function index(): JsonResponse
    {
        $methods = PaymentMethodConfig::orderBy('label')->get();

        return response()->json(['data' => PaymentMethodConfigResource::collection($methods)]);
    }

    public function store(StorePaymentMethodRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $method = PaymentMethodConfig::create([
            'key'          => strtolower($validated['key']),
            'label'        => $validated['label'],
            'auto_confirm' => $validated['auto_confirm'] ?? false,
            'active'       => true,
        ]);

        return response()->json(['data' => new PaymentMethodConfigResource($method)], 201);
    }

    public function update(UpdatePaymentMethodRequest $request, PaymentMethodConfig $paymentMethod): JsonResponse
    {
        if (in_array($paymentMethod->key, ['cash', 'mobile_money'], true)) {
            return response()->json(['code' => 'METHOD_LOCKED', 'message' => 'Les méthodes de paiement par défaut ne peuvent pas être modifiées.', 'champ' => null], 409);
        }

        $validated = $request->validated();

        $paymentMethod->update($validated);

        return response()->json(['data' => new PaymentMethodConfigResource($paymentMethod->fresh() ?? $paymentMethod)]);
    }
}
