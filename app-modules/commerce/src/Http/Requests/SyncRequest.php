<?php

declare(strict_types=1);

namespace Modules\Commerce\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class SyncRequest extends FormRequest
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
            'sales'                                    => 'required|array',
            'sales.*.idempotency_key'                  => 'required|string',
            'sales.*.cash_session_id'                  => 'required|uuid',
            'sales.*.lines'                             => 'required|array|min:1',
            'sales.*.lines.*.product_id'                => 'required|uuid',
            'sales.*.lines.*.quantity'                  => 'required|integer|min:1',
            'sales.*.lines.*.designation'               => 'required|string',
            'sales.*.lines.*.unit_price'                => 'required|integer|min:0',
            'sales.*.lines.*.vat_rate'                  => 'required|string',
            'sales.*.lines.*.line_total_excluding_tax'  => 'required|integer|min:0',
            'sales.*.lines.*.line_total_tax'            => 'required|integer|min:0',
            'sales.*.lines.*.line_total_including_tax'  => 'required|integer|min:0',
        ];
    }
}
