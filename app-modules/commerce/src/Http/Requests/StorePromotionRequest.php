<?php

declare(strict_types=1);

namespace Modules\Commerce\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class StorePromotionRequest extends FormRequest
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
            'name'       => ['required', 'string', 'max:255'],
            'type'       => ['required', 'string', 'in:percent,fixed_amount'],
            'value'      => ['required', 'integer', 'min:1'],
            'scope'      => ['required', 'string', 'in:product,family'],
            'scope_id'   => ['sometimes', 'nullable', 'uuid'],
            'starts_at'  => ['sometimes', 'nullable', 'date'],
            'ends_at'    => ['sometimes', 'nullable', 'date', 'after_or_equal:starts_at'],
            'cumulative' => ['sometimes', 'boolean'],
            'active'     => ['sometimes', 'boolean'],
        ];
    }
}
