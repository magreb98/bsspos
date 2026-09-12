<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

final class UpdateAdminUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        // The route parameter is named `adminUser` in routes/admin.php
        // (`admin-users/{adminUser}`), even though the controller method
        // receives it into a plain `string $id` argument.
        $id = $this->route('adminUser');

        return [
            'name'           => ['sometimes', 'string', 'max:255'],
            'email'          => ['sometimes', 'email', 'unique:admin_users,email,' . $id],
            'active'         => ['sometimes', 'boolean'],
            'password'       => ['sometimes', 'string', 'min:8'],
            'is_super_admin' => ['sometimes', 'boolean'],
        ];
    }
}
