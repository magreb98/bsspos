<?php

declare(strict_types=1);

namespace Modules\Commerce\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class RecordInvoicePaymentRequest extends FormRequest
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
            'amount'       => ['required', 'integer', 'min:1'],
            'payment_date' => ['sometimes', 'date'],
            'method'       => ['sometimes', 'string', 'max:50'],
            'reference'    => ['sometimes', 'nullable', 'string', 'max:100'],
        ];
    }
}
