<?php

declare(strict_types=1);

namespace Modules\Commerce\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class UpdateFamilyRequest extends FormRequest
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
            'name'      => ['sometimes', 'string', 'max:150'],
            'parent_id' => ['sometimes', 'nullable', 'uuid'],
            'active'    => ['sometimes', 'boolean'],
        ];
    }
}
