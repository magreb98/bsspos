<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

final class StoreTenantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name'                     => ['required', 'string', 'max:255'],
            'domain'                   => ['required', 'string', 'max:255'],
            'initial_admin'            => ['required', 'array'],
            'initial_admin.first_name' => ['required', 'string'],
            'initial_admin.last_name'  => ['required', 'string'],
            'initial_admin.phone'      => ['required', 'string'],
            'initial_admin.password'   => ['required', 'string', 'min:8'],
        ];
    }
}
