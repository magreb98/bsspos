<?php

declare(strict_types=1);

namespace Modules\SecteurElectronique\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class StoreSerialUnitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'product_id'    => ['required', 'uuid'],
            'serial_number' => ['required', 'string', 'max:100'],
        ];
    }
}
