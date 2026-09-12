<?php

declare(strict_types=1);

namespace Modules\Commerce\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Modules\Commerce\Http\Requests\StoreCurrencyRequest;
use Modules\Commerce\Http\Requests\UpdateCurrencyRequest;
use Modules\Commerce\Http\Resources\CurrencyResource;
use Modules\Commerce\Internal\Models\Currency;

final class CurrencyController
{
    public function index(): JsonResponse
    {
        $currencies = Currency::where('active', true)->orderBy('code')->get();

        return response()->json(['data' => CurrencyResource::collection($currencies)]);
    }

    public function store(StoreCurrencyRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $currency = Currency::create([
            'code'          => strtoupper((string) $validated['code']),
            'name'          => $validated['name'],
            'symbol'        => $validated['symbol'],
            'exchange_rate' => (int) $validated['exchange_rate'],
            'is_base'       => false,
            'active'        => true,
        ]);

        return response()->json(['data' => new CurrencyResource($currency)], 201);
    }

    public function update(UpdateCurrencyRequest $request, Currency $currency): JsonResponse
    {
        if ($currency->is_base) {
            return response()->json(['code' => 'BASE_CURRENCY', 'message' => 'The base currency (XAF) cannot be modified.', 'champ' => null], 422);
        }

        $validated = $request->validated();

        $currency->update($validated);

        $freshCurrency = $currency->fresh();

        return response()->json(['data' => $freshCurrency !== null ? new CurrencyResource($freshCurrency) : []]);
    }
}
