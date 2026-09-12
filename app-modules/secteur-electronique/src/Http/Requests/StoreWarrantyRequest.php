<?php

declare(strict_types=1);

namespace Modules\SecteurElectronique\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class StoreWarrantyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'serial_unit_id'  => ['required', 'uuid'],
            'sale_line_id'    => ['required', 'uuid'],
            'duration_months' => ['required', 'integer', 'min:1'],
        ];
    }
}
