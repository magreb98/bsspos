<?php

declare(strict_types=1);

namespace Modules\Commerce\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class StoreSupplierOrderRequest extends FormRequest
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
            'supplier_id'        => 'required|uuid',
            'lines'              => 'required|array|min:1',
            'lines.*.product_id' => 'required|uuid',
            'lines.*.quantity'   => 'required|integer|min:1',
            'lines.*.unit_cost'  => 'required|integer|min:0',
        ];
    }
}
