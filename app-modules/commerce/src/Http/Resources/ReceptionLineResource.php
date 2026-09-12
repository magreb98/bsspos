<?php

declare(strict_types=1);

namespace Modules\Commerce\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class ReceptionLineResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'                 => $this->id,
            'reception_id'       => $this->reception_id,
            'product_id'         => $this->product_id,
            'quantity_expected'  => $this->quantity_expected,
            'quantity_received'  => $this->quantity_received,
            'unit_cost'          => $this->unit_cost,
            'created_at'         => $this->created_at,
            'updated_at'         => $this->updated_at,
            'product'            => new ProductResource($this->whenLoaded('product')),
        ];
    }
}
