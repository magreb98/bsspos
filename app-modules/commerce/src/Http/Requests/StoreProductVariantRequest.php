<?php

declare(strict_types=1);

namespace Modules\Commerce\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class StoreProductVariantRequest extends FormRequest
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
            'label'     => ['required', 'string', 'max:255'],
            'reference' => ['required', 'string', 'max:100', 'unique:product_variants,reference'],
        ];
    }
}
