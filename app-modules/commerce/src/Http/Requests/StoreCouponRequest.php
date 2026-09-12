<?php

declare(strict_types=1);

namespace Modules\Commerce\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class StoreCouponRequest extends FormRequest
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
            'code'     => ['required', 'string', 'max:50', 'unique:coupons,code'],
            'max_uses' => ['sometimes', 'nullable', 'integer', 'min:1'],
        ];
    }
}
