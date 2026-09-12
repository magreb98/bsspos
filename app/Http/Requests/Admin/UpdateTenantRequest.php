<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

final class UpdateTenantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name'   => ['sometimes', 'string', 'max:255'],
            'status' => ['sometimes', 'string', 'in:actif,suspendu'],
        ];
    }
}
