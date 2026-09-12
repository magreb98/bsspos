<?php

declare(strict_types=1);

namespace Modules\Commerce\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class MarginReportRequest extends FormRequest
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
            'from'      => ['sometimes', 'date'],
            'to'        => ['sometimes', 'date'],
            'family_id' => ['sometimes', 'uuid'],
        ];
    }
}
