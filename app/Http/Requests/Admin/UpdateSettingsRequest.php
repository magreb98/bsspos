<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

final class UpdateSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'platform_name'     => ['sometimes', 'string', 'max:100'],
            'contact_email'     => ['sometimes', 'email', 'max:255'],
            'max_tenants'       => ['sometimes', 'integer', 'min:0'],
            'maintenance_mode'  => ['sometimes', 'boolean'],
            'provisioning_auto' => ['sometimes', 'boolean'],
            'support_url'       => ['sometimes', 'url', 'max:255'],
        ];
    }
}
