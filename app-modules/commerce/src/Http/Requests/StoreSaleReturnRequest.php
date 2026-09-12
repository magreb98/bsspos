<?php

declare(strict_types=1);

namespace Modules\Commerce\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class StoreSaleReturnRequest extends FormRequest
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
            'lines'                => ['sometimes', 'array'],
            'lines.*.sale_line_id' => ['required_with:lines', 'uuid'],
            'lines.*.quantity'     => ['required_with:lines', 'integer', 'min:1'],
        ];
    }
}
