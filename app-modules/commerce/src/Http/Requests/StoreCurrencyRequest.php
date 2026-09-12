<?php

declare(strict_types=1);

namespace Modules\Commerce\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class StoreCurrencyRequest extends FormRequest
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
            'code'          => ['required', 'string', 'size:3', 'unique:currencies,code'],
            'name'          => ['required', 'string', 'max:100'],
            'symbol'        => ['required', 'string', 'max:10'],
            'exchange_rate' => ['required', 'integer', 'min:1'],
        ];
    }
}
