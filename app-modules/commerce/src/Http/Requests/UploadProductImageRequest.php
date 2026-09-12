<?php

declare(strict_types=1);

namespace Modules\Commerce\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class UploadProductImageRequest extends FormRequest
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
            'image'    => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'position' => ['sometimes', 'integer', 'min:0'],
        ];
    }
}
