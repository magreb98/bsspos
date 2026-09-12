<?php

declare(strict_types=1);

namespace Modules\Commerce\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class StoreUserRequest extends FormRequest
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
            'name'                => ['required', 'string', 'max:200'],
            'phone'               => ['required', 'string', 'max:20', 'unique:members,phone'],
            'role'                => ['required', 'string', 'in:vendeur,gerant,gérant,proprietaire'],
            'point_of_sale_ids'   => ['nullable', 'array'],
            'point_of_sale_ids.*' => ['string', 'exists:points_of_sale,id'],
        ];
    }
}
