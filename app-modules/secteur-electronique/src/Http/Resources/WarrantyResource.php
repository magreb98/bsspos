<?php

declare(strict_types=1);

namespace Modules\SecteurElectronique\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class WarrantyResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id'               => $this->id,
            'serial_unit_id'   => $this->serial_unit_id,
            'sale_line_id'     => $this->sale_line_id,
            'starts_on'        => $this->starts_on,
            'expires_on'       => $this->expires_on,
            'duration_months'  => $this->duration_months,
            'created_at'       => $this->created_at,
            'updated_at'       => $this->updated_at,
        ];
    }
}
