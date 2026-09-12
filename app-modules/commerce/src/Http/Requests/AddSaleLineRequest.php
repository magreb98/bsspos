<?php

declare(strict_types=1);

namespace Modules\Commerce\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class AddSaleLineRequest extends FormRequest
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
            'product_id'       => ['required', 'uuid'],
            'quantity'         => ['required', 'integer', 'min:1'],
            'unit_price'       => ['sometimes', 'integer', 'min:0'],
            'designation'      => ['sometimes', 'nullable', 'string', 'max:255'],
            'discount_percent' => ['sometimes', 'integer', 'min:0', 'max:100'],
            'discount_amount'  => ['sometimes', 'integer', 'min:0'],
        ];
    }
}
