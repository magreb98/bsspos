<?php

declare(strict_types=1);

namespace Modules\Commerce\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class StoreProductRequest extends FormRequest
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
            'reference'     => ['required', 'string', 'max:100'],
            'label'         => ['required', 'string', 'max:255'],
            'family_id'     => ['required', 'uuid'],
            'selling_price' => ['required', 'integer', 'min:0'],
            'vat_rate'      => ['required', 'numeric', 'min:0', 'max:100'],
            'granularity'   => ['required', 'string', 'in:quantity,variant,serial,batch,service'],
            'attributes'    => ['sometimes', 'nullable', 'array'],
        ];
    }
}
