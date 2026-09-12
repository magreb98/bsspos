<?php

declare(strict_types=1);

namespace Modules\Commerce\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class UpdatePaymentMethodRequest extends FormRequest
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
            'label'        => ['sometimes', 'string', 'max:100'],
            'auto_confirm' => ['sometimes', 'boolean'],
            'active'       => ['sometimes', 'boolean'],
        ];
    }
}
