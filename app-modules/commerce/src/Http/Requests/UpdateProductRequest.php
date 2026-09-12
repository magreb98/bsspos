<?php

declare(strict_types=1);

namespace Modules\Commerce\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class UpdateProductRequest extends FormRequest
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
            'label'         => ['sometimes', 'string', 'max:255'],
            'family_id'     => ['sometimes', 'uuid'],
            'selling_price' => ['sometimes', 'integer', 'min:0'],
            'vat_rate'      => ['sometimes', 'numeric', 'min:0', 'max:100'],
            'granularity'   => ['sometimes', 'string', 'in:quantity,variant,serial,batch,service'],
            'attributes'    => ['sometimes', 'nullable', 'array'],
            'active'        => ['sometimes', 'boolean'],
        ];
    }
}
