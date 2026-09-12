<?php

declare(strict_types=1);

namespace Modules\Commerce\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class CouponResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'           => $this->id,
            'code'         => $this->code,
            'promotion_id' => $this->promotion_id,
            'max_uses'     => $this->max_uses,
            'times_used'   => $this->times_used,
            'created_at'   => $this->created_at,
            'updated_at'   => $this->updated_at,
            'promotion'    => new PromotionResource($this->whenLoaded('promotion')),
        ];
    }
}
