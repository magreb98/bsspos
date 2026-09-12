<?php

declare(strict_types=1);

namespace Modules\Commerce\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class StoreReceptionRequest extends FormRequest
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
            'point_of_sale_id'          => 'required|uuid',
            'lines'                     => 'required|array|min:1',
            'lines.*.product_id'        => 'required|uuid',
            'lines.*.quantity_expected' => 'required|integer|min:0',
            'lines.*.quantity_received' => 'required|integer|min:0',
            'lines.*.unit_cost'         => 'required|integer|min:0',
        ];
    }
}
