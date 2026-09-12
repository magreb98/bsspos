<?php

declare(strict_types=1);

namespace Modules\Commerce\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class UpdateInvoiceSettingRequest extends FormRequest
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
            'company_name' => ['sometimes', 'string', 'max:255'],
            'niu'          => ['sometimes', 'nullable', 'string', 'max:50'],
            'rccm'         => ['sometimes', 'nullable', 'string', 'max:50'],
            'address'      => ['sometimes', 'nullable', 'string', 'max:500'],
            'phone'        => ['sometimes', 'nullable', 'string', 'max:30'],
        ];
    }
}
