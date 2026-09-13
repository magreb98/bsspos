<?php

declare(strict_types=1);

namespace Modules\Commerce\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class PointOfSaleResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'                     => $this->id,
            'name'                   => $this->name,
            'organizational_unit_id' => $this->organizational_unit_id,
            'active'                 => $this->active,
            'created_at'             => $this->created_at,
            'updated_at'             => $this->updated_at,
            'organizational_unit'    => new OrganizationalUnitResource($this->whenLoaded('organizationalUnit')),
            'cash_registers'         => CashRegisterResource::collection($this->whenLoaded('cashRegisters')),
        ];
    }
}
