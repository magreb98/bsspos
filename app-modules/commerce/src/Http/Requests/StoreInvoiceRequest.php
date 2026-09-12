<?php

declare(strict_types=1);

namespace Modules\Commerce\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class StoreInvoiceRequest extends FormRequest
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
            'client_id'  => ['required', 'uuid'],
            'issue_date' => ['sometimes', 'date'],
            'due_date'   => ['sometimes', 'nullable', 'date', 'after_or_equal:issue_date'],
            'total_ht'   => ['required', 'integer', 'min:0'],
            'total_vat'  => ['sometimes', 'integer', 'min:0'],
            'total_ttc'  => ['required', 'integer', 'min:1'],
            'notes'      => ['sometimes', 'nullable', 'string', 'max:2000'],
        ];
    }
}
