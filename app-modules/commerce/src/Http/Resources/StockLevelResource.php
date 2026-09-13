<?php

declare(strict_types=1);

namespace Modules\Commerce\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class StockLevelResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'                 => $this->id,
            'point_of_sale_id'   => $this->point_of_sale_id,
            'product_id'         => $this->product_id,
            'product_variant_id' => $this->product_variant_id,
            'quantity'           => $this->quantity,
            'minimum_quantity'   => $this->minimum_quantity,
            'created_at'         => $this->created_at,
            'updated_at'         => $this->updated_at,
            'point_of_sale'      => new PointOfSaleResource($this->whenLoaded('pointOfSale')),
            'product'            => new ProductResource($this->whenLoaded('product')),
            'product_variant'    => new ProductVariantResource($this->whenLoaded('productVariant')),
        ];
    }
}
