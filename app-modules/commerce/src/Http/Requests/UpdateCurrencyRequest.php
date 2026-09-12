<?php

declare(strict_types=1);

namespace Modules\Commerce\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class UpdateCurrencyRequest extends FormRequest
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
            'exchange_rate' => ['sometimes', 'integer', 'min:1'],
            'active'        => ['sometimes', 'boolean'],
            'name'          => ['sometimes', 'string', 'max:100'],
            'symbol'        => ['sometimes', 'string', 'max:10'],
        ];
    }
}
