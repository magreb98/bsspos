<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

final class AuditLogExportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'tenant_id' => ['sometimes', 'nullable', 'uuid'],
            'tool'      => ['sometimes', 'nullable', 'string', 'max:255'],
            'from'      => ['sometimes', 'nullable', 'date'],
            'to'        => ['sometimes', 'nullable', 'date'],
        ];
    }
}
