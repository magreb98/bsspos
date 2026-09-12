<?php

declare(strict_types=1);

namespace Modules\Commerce\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class InventoryCountResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'               => $this->id,
            'point_of_sale_id' => $this->point_of_sale_id,
            'counted_at'       => $this->counted_at,
            'notes'            => $this->notes,
            'created_at'       => $this->created_at,
            'updated_at'       => $this->updated_at,
            'pointOfSale'      => new PointOfSaleResource($this->whenLoaded('pointOfSale')),
            'lines'            => InventoryCountLineResource::collection($this->whenLoaded('lines')),
        ];
    }
}
