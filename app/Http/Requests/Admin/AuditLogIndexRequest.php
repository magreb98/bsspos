<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

final class AuditLogIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'per_page'  => ['sometimes', 'integer', 'min:1'],
            'page'      => ['sometimes', 'integer', 'min:1'],
            'tenant_id' => ['sometimes', 'nullable', 'uuid'],
            'tool'      => ['sometimes', 'nullable', 'string', 'max:255'],
            'from'      => ['sometimes', 'nullable', 'date'],
            'to'        => ['sometimes', 'nullable', 'date'],
        ];
    }
}
