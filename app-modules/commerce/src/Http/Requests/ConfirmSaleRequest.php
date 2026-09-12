<?php

declare(strict_types=1);

namespace Modules\Commerce\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class ConfirmSaleRequest extends FormRequest
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
            'coupon_code'         => ['sometimes', 'nullable', 'string'],
            'loyalty_points_used' => ['sometimes', 'integer', 'min:0'],
            'customer_credit_id'  => ['sometimes', 'nullable', 'uuid'],
        ];
    }
}
