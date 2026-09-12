<?php

declare(strict_types=1);

namespace Modules\Commerce\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class TransferLineResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'transfer_id' => $this->transfer_id,
            'product_id'  => $this->product_id,
            'quantity'    => $this->quantity,
            'created_at'  => $this->created_at,
            'updated_at'  => $this->updated_at,
            'product'     => new ProductResource($this->whenLoaded('product')),
        ];
    }
}
