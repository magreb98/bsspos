<?php

declare(strict_types=1);

namespace Modules\Commerce\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class ReceptionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'                 => $this->id,
            'supplier_order_id'  => $this->supplier_order_id,
            'point_of_sale_id'   => $this->point_of_sale_id,
            'received_at'        => $this->received_at,
            'notes'              => $this->notes,
            'created_at'         => $this->created_at,
            'updated_at'         => $this->updated_at,
            'order'              => new SupplierOrderResource($this->whenLoaded('order')),
            'pointOfSale'        => new PointOfSaleResource($this->whenLoaded('pointOfSale')),
            'lines'              => ReceptionLineResource::collection($this->whenLoaded('lines')),
        ];
    }
}
