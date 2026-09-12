<?php

declare(strict_types=1);

namespace Modules\SecteurElectronique\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class StoreServiceTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'serial_unit_id' => ['required', 'uuid'],
            'description'    => ['required', 'string'],
        ];
    }
}
