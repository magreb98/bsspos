<?php

declare(strict_types=1);

namespace Modules\SecteurElectronique\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class SerialUnitResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id'            => $this->id,
            'product_id'    => $this->product_id,
            'serial_number' => $this->serial_number,
            'status'        => $this->status,
            'sale_line_id'  => $this->sale_line_id,
            'created_at'    => $this->created_at,
            'updated_at'    => $this->updated_at,
        ];
    }
}
