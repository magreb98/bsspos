<?php

declare(strict_types=1);

namespace Modules\Commerce\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class StoreStockMovementRequest extends FormRequest
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
            'product_id'       => ['required', 'uuid'],
            'point_of_sale_id' => ['required', 'uuid'],
            // Positive to increase stock, negative to decrease — never zero.
            'quantity'         => ['required', 'integer', 'not_in:0'],
        ];
    }
}
