<?php

declare(strict_types=1);

namespace Modules\Commerce\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Commerce\Internal\Models\PaymentMethodConfig;

final class StorePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $activeKeys = PaymentMethodConfig::where('active', true)->pluck('key')->toArray();

        return [
            'method'        => ['required', 'string', 'in:' . implode(',', $activeKeys)],
            'amount'        => ['required', 'integer', 'min:1'],
            'reference'     => ['sometimes', 'nullable', 'string', 'max:100'],
            'currency_code' => ['sometimes', 'string', 'size:3'],
            'exchange_rate' => ['sometimes', 'integer', 'min:1'],
        ];
    }
}
