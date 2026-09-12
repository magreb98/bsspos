<?php

declare(strict_types=1);

namespace Modules\Commerce\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class StockMovementResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'               => $this->id,
            'point_of_sale_id' => $this->point_of_sale_id,
            'product_id'       => $this->product_id,
            'sale_line_id'     => $this->sale_line_id,
            'quantity'         => $this->quantity,
            'occurred_at'      => $this->occurred_at,
            'created_at'       => $this->created_at,
            'updated_at'       => $this->updated_at,
            'product'          => new ProductResource($this->whenLoaded('product')),
            'pointOfSale'      => new PointOfSaleResource($this->whenLoaded('pointOfSale')),
        ];
    }
}
