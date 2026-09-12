<?php

declare(strict_types=1);

namespace Modules\Commerce\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class StockRotationReportRequest extends FormRequest
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
            'from'             => ['sometimes', 'date'],
            'to'               => ['sometimes', 'date'],
            'point_of_sale_id' => ['sometimes', 'uuid'],
        ];
    }
}
