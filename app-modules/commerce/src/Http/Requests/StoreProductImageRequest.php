<?php

declare(strict_types=1);

namespace Modules\Commerce\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class StoreProductImageRequest extends FormRequest
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
            'url'      => ['required', 'url', 'max:2048', 'starts_with:http://,https://'],
            'position' => ['sometimes', 'integer', 'min:0'],
        ];
    }
}
