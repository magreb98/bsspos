<?php

declare(strict_types=1);

namespace Modules\Commerce\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class CustomerCreditResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'                => $this->id,
            'customer_id'       => $this->customer_id,
            'return_sale_id'    => $this->return_sale_id,
            'original_amount'   => $this->original_amount,
            'remaining_amount'  => $this->remaining_amount,
            'created_at'        => $this->created_at,
            'updated_at'        => $this->updated_at,
            'customer'          => new CustomerResource($this->whenLoaded('customer')),
            'returnSale'        => new SaleResource($this->whenLoaded('returnSale')),
        ];
    }
}
