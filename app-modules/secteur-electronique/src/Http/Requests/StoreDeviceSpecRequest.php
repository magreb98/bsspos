<?php

declare(strict_types=1);

namespace Modules\SecteurElectronique\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class StoreDeviceSpecRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'product_id'         => ['required', 'uuid'],
            'category'           => ['required', 'string'],
            'brand'              => ['required', 'string', 'max:100'],
            'model'              => ['required', 'string', 'max:150'],
            'warranty_months'    => ['required', 'integer', 'min:1'],
            'imei_required'      => ['sometimes', 'boolean'],
            'screen_size_inches' => ['sometimes', 'nullable', 'numeric'],
            'screen_resolution'  => ['sometimes', 'nullable', 'string', 'max:50'],
            'panel_type'         => ['sometimes', 'nullable', 'string', 'max:50'],
            'processor'          => ['sometimes', 'nullable', 'string', 'max:100'],
            'ram_gb'             => ['sometimes', 'nullable', 'integer', 'min:1'],
            'storage_gb'         => ['sometimes', 'nullable', 'integer', 'min:1'],
            'bluetooth'          => ['sometimes', 'nullable', 'boolean'],
            'wifi'               => ['sometimes', 'nullable', 'boolean'],
            'nfc'                => ['sometimes', 'nullable', 'boolean'],
            'cellular_network'   => ['sometimes', 'nullable', 'string', 'max:50'],
            'battery_mah'        => ['sometimes', 'nullable', 'integer', 'min:1'],
            'main_camera_mp'     => ['sometimes', 'nullable', 'numeric'],
            'power_watts'        => ['sometimes', 'nullable', 'numeric'],
            'operating_system'   => ['sometimes', 'nullable', 'string', 'max:100'],
            'weight_grams'       => ['sometimes', 'nullable', 'integer'],
            'color'              => ['sometimes', 'nullable', 'string', 'max:50'],
            'model_year'         => ['sometimes', 'nullable', 'integer'],
            'additional_specs'   => ['sometimes', 'nullable', 'array'],
        ];
    }
}
