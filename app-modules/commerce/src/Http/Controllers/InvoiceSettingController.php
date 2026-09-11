<?php

declare(strict_types=1);

namespace Modules\Commerce\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Commerce\Internal\Models\InvoiceSetting;

final class InvoiceSettingController
{
    public function show(): JsonResponse
    {
        $setting = InvoiceSetting::first();

        if ($setting === null) {
            return response()->json(['data' => null]);
        }

        return response()->json(['data' => $setting->toArray()]);
    }

    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'company_name' => ['sometimes', 'string', 'max:255'],
            'niu'          => ['sometimes', 'nullable', 'string', 'max:50'],
            'rccm'         => ['sometimes', 'nullable', 'string', 'max:50'],
            'address'      => ['sometimes', 'nullable', 'string', 'max:500'],
            'phone'        => ['sometimes', 'nullable', 'string', 'max:30'],
        ]);

        $setting = InvoiceSetting::first();

        if ($setting === null) {
            $setting = InvoiceSetting::create($validated);
        } else {
            $setting->update($validated);
        }

        return response()->json(['data' => $setting->fresh()?->toArray() ?? $setting->toArray()]);
    }
}
