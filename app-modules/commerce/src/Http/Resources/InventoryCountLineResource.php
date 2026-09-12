<?php

declare(strict_types=1);

namespace Modules\Commerce\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class InventoryCountLineResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'                   => $this->id,
            'inventory_count_id'   => $this->inventory_count_id,
            'product_id'           => $this->product_id,
            'theoretical_quantity' => $this->theoretical_quantity,
            'counted_quantity'     => $this->counted_quantity,
            'adjustment'           => $this->adjustment,
            'created_at'           => $this->created_at,
            'updated_at'           => $this->updated_at,
            'product'              => new ProductResource($this->whenLoaded('product')),
        ];
    }
}
