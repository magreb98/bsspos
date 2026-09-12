<?php

declare(strict_types=1);

namespace Modules\Commerce\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class UpdateUserRequest extends FormRequest
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
            'role'   => ['sometimes', 'string', 'in:vendeur,gerant,gérant,proprietaire'],
            'active' => ['sometimes', 'boolean'],
        ];
    }
}
