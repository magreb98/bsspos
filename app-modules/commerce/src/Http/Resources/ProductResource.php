<?php

declare(strict_types=1);

namespace Modules\Commerce\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class ProductResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'            => $this->id,
            'reference'     => $this->reference,
            'label'         => $this->label,
            'family_id'     => $this->family_id,
            'selling_price' => $this->selling_price,
            'vat_rate'      => $this->vat_rate,
            'granularity'   => $this->granularity,
            'attributes'    => $this->attributes,
            'active'        => $this->active,
            'created_at'    => $this->created_at,
            'updated_at'    => $this->updated_at,
            'family'        => new FamilyResource($this->whenLoaded('family')),
            'images'        => ProductImageResource::collection($this->whenLoaded('images')),
        ];
    }
}
