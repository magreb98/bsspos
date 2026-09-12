<?php

declare(strict_types=1);

namespace Modules\Commerce\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class StorePaymentMethodRequest extends FormRequest
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
        return [
            'key'          => ['required', 'string', 'max:50', 'unique:payment_methods,key'],
            'label'        => ['required', 'string', 'max:100'],
            'auto_confirm' => ['sometimes', 'boolean'],
        ];
    }
}
