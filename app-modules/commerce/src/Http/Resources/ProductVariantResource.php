<?php

declare(strict_types=1);

namespace Modules\Commerce\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class ProductVariantResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'product_id'  => $this->product_id,
            'label'       => $this->label,
            'reference'   => $this->reference,
            'active'      => $this->active,
            'created_at'  => $this->created_at,
            'updated_at'  => $this->updated_at,
            'product'     => new ProductResource($this->whenLoaded('product')),
            'stockLevels' => StockLevelResource::collection($this->whenLoaded('stockLevels')),
        ];
    }
}
