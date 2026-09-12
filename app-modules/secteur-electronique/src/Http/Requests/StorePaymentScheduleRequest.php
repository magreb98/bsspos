<?php

declare(strict_types=1);

namespace Modules\SecteurElectronique\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class StorePaymentScheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'sale_id'               => ['required', 'uuid'],
            'deposit'               => ['required', 'integer', 'min:0'],
            'installments'          => ['required', 'array', 'min:1'],
            'installments.*.amount' => ['required', 'integer', 'min:1'],
            'installments.*.due_on' => ['required', 'string', 'date_format:Y-m-d'],
        ];
    }
}
