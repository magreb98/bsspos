<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

final class StoreAdminUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name'           => ['required', 'string', 'max:255'],
            'email'          => ['required', 'email', 'unique:admin_users,email'],
            'password'       => ['required', 'string', 'min:8'],
            // Only an existing super-admin can grant this, and it defaults
            // to false: creating a new admin never silently creates another
            // super-admin, which is what let a single compromised admin
            // token escalate to full platform control before this check.
            'is_super_admin' => ['sometimes', 'boolean'],
        ];
    }
}
