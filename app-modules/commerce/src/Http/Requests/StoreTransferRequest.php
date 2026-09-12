<?php

declare(strict_types=1);

namespace Modules\Commerce\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class StoreTransferRequest extends FormRequest
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
            'source_pos_id'      => 'required|uuid',
            'destination_pos_id' => 'required|uuid',
            'notes'              => 'nullable|string|max:1000',
            'lines'              => 'required|array|min:1',
            'lines.*.product_id' => 'required|uuid',
            'lines.*.quantity'   => 'required|integer|min:1',
        ];
    }
}
