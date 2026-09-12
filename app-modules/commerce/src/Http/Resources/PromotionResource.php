<?php

declare(strict_types=1);

namespace Modules\Commerce\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class PromotionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'         => $this->id,
            'name'       => $this->name,
            'type'       => $this->type,
            'value'      => $this->value,
            'scope'      => $this->scope,
            'scope_id'   => $this->scope_id,
            'starts_at'  => $this->starts_at,
            'ends_at'    => $this->ends_at,
            'cumulative' => $this->cumulative,
            'active'     => $this->active,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'coupons'    => CouponResource::collection($this->whenLoaded('coupons')),
        ];
    }
}
