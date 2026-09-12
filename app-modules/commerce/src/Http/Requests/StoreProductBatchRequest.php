<?php

declare(strict_types=1);

namespace Modules\Commerce\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class StoreProductBatchRequest extends FormRequest
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
            'batch_number'       => ['required', 'string', 'max:100', 'unique:product_batches,batch_number'],
            'quantity'           => ['required', 'integer', 'min:0'],
            'expiry_date'        => ['sometimes', 'nullable', 'date'],
            'product_variant_id' => ['sometimes', 'nullable', 'uuid'],
        ];
    }
}
