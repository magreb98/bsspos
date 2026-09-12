<?php

declare(strict_types=1);

namespace Modules\Commerce\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class StoreQuoteRequest extends FormRequest
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
            'client_id'       => ['sometimes', 'nullable', 'uuid', 'exists:customers,id'],
            'valid_until'     => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'idempotency_key' => ['sometimes', 'nullable', 'string', 'max:100'],
        ];
    }
}
