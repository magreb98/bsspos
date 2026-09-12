<?php

declare(strict_types=1);

namespace Modules\SecteurElectronique\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class DeviceSpecResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id'                 => $this->id,
            'product_id'         => $this->product_id,
            'category'           => $this->category,
            'brand'              => $this->brand,
            'model'              => $this->model,
            'color'              => $this->color,
            'model_year'         => $this->model_year,
            'imei_required'      => $this->imei_required,
            'warranty_months'    => $this->warranty_months,
            'screen_size_inches' => $this->screen_size_inches,
            'screen_resolution'  => $this->screen_resolution,
            'panel_type'         => $this->panel_type,
            'processor'          => $this->processor,
            'ram_gb'             => $this->ram_gb,
            'storage_gb'         => $this->storage_gb,
            'bluetooth'          => $this->bluetooth,
            'wifi'               => $this->wifi,
            'nfc'                => $this->nfc,
            'cellular_network'   => $this->cellular_network,
            'battery_mah'        => $this->battery_mah,
            'main_camera_mp'     => $this->main_camera_mp,
            'power_watts'        => $this->power_watts,
            'operating_system'   => $this->operating_system,
            'weight_grams'       => $this->weight_grams,
            'additional_specs'   => $this->additional_specs,
            'created_at'         => $this->created_at,
            'updated_at'         => $this->updated_at,
        ];
    }
}
