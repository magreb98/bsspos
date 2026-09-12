<?php

declare(strict_types=1);

namespace Modules\Commerce\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class ProductBatchResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'                 => $this->id,
            'product_id'         => $this->product_id,
            'product_variant_id' => $this->product_variant_id,
            'batch_number'       => $this->batch_number,
            'expiry_date'        => $this->expiry_date,
            'quantity'           => $this->quantity,
            'created_at'         => $this->created_at,
            'updated_at'         => $this->updated_at,
            'product'            => new ProductResource($this->whenLoaded('product')),
            'variant'            => new ProductVariantResource($this->whenLoaded('variant')),
        ];
    }
}
