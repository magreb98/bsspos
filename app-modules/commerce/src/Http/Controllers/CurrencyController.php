<?php

declare(strict_types=1);

namespace Modules\Commerce\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Commerce\Internal\Models\Currency;

final class CurrencyController
{
    public function index(): JsonResponse
    {
        $currencies = Currency::where('active', true)->orderBy('code')->get();

        return response()->json(['data' => $currencies->toArray()]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code'          => ['required', 'string', 'size:3', 'unique:currencies,code'],
            'name'          => ['required', 'string', 'max:100'],
            'symbol'        => ['required', 'string', 'max:10'],
            'exchange_rate' => ['required', 'integer', 'min:1'],
        ]);

        $currency = Currency::create([
            'code'          => strtoupper((string) $validated['code']),
            'name'          => $validated['name'],
            'symbol'        => $validated['symbol'],
            'exchange_rate' => (int) $validated['exchange_rate'],
            'is_base'       => false,
            'active'        => true,
        ]);

        return response()->json(['data' => $currency->toArray()], 201);
    }

    public function update(Request $request, Currency $currency): JsonResponse
    {
        if ($currency->is_base) {
            return response()->json(['code' => 'BASE_CURRENCY', 'message' => 'The base currency (XAF) cannot be modified.', 'champ' => null], 422);
        }

        $validated = $request->validate([
            'exchange_rate' => ['sometimes', 'integer', 'min:1'],
            'active'        => ['sometimes', 'boolean'],
            'name'          => ['sometimes', 'string', 'max:100'],
            'symbol'        => ['sometimes', 'string', 'max:10'],
        ]);

        $currency->update($validated);

        return response()->json(['data' => $currency->fresh()?->toArray() ?? []]);
    }
}
