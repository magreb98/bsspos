<?php

declare(strict_types=1);

namespace Modules\Commerce\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Modules\Commerce\Http\Requests\UpdateInvoiceSettingRequest;
use Modules\Commerce\Http\Resources\InvoiceSettingResource;
use Modules\Commerce\Internal\Models\InvoiceSetting;

final class InvoiceSettingController
{
    public function show(): JsonResponse
    {
        $setting = InvoiceSetting::first();

        if ($setting === null) {
            return response()->json(['data' => null]);
        }

        return response()->json(['data' => new InvoiceSettingResource($setting)]);
    }

    public function update(UpdateInvoiceSettingRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $setting = InvoiceSetting::first();

        if ($setting === null) {
            $setting = InvoiceSetting::create($validated);
        } else {
            $setting->update($validated);
        }

        return response()->json(['data' => new InvoiceSettingResource($setting->fresh() ?? $setting)]);
    }
}
