<?php

declare(strict_types=1);

namespace Modules\Commerce\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class StoreInventoryCountRequest extends FormRequest
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
            'point_of_sale_id'         => 'required|uuid',
            'notes'                    => 'nullable|string|max:1000',
            'lines'                    => 'required|array|min:1',
            'lines.*.product_id'       => 'required|uuid',
            'lines.*.counted_quantity' => 'required|integer|min:0',
        ];
    }
}
