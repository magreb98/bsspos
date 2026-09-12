<?php

declare(strict_types=1);

namespace Modules\Commerce\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class StoreSaleRequest extends FormRequest
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
            'cash_session_id' => ['required', 'uuid'],
            'client_id'       => ['sometimes', 'nullable', 'uuid'],
            'idempotency_key' => ['sometimes', 'nullable', 'string', 'max:100'],
        ];
    }
}
