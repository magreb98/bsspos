<?php

declare(strict_types=1);

namespace Modules\SecteurElectronique\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class UpdateServiceTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'status'      => ['sometimes', 'string', 'in:open,in_repair,closed'],
            'description' => ['sometimes', 'string'],
            'repair_cost' => ['sometimes', 'nullable', 'integer', 'min:0'],
        ];
    }
}
