<?php

declare(strict_types=1);

namespace Modules\Commerce\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class SaleLineResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'                       => $this->id,
            'sale_id'                  => $this->sale_id,
            'product_id'               => $this->product_id,
            'designation'              => $this->designation,
            'unit_price'               => $this->unit_price,
            'vat_rate'                 => $this->vat_rate,
            'quantity'                 => $this->quantity,
            'line_total_excluding_tax' => $this->line_total_excluding_tax,
            'line_total_tax'           => $this->line_total_tax,
            'line_total_including_tax' => $this->line_total_including_tax,
            'allocations'              => $this->allocations,
            'discount_amount'          => $this->discount_amount,
            'coupon_id'                => $this->coupon_id,
            'created_at'               => $this->created_at,
            'updated_at'               => $this->updated_at,
            'sale'                     => new SaleResource($this->whenLoaded('sale')),
            'product'                  => new ProductResource($this->whenLoaded('product')),
        ];
    }
}
