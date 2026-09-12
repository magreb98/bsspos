<?php

declare(strict_types=1);

namespace Modules\Commerce\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class UpdateCustomerRequest extends FormRequest
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
            'name'         => ['sometimes', 'string', 'max:255'],
            'phone'        => ['sometimes', 'nullable', 'string', 'max:30'],
            'credit_limit' => ['sometimes', 'integer', 'min:0'],
            'active'       => ['sometimes', 'boolean'],
        ];
    }
}
