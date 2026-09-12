<?php

declare(strict_types=1);

namespace Modules\Commerce\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class PaymentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'              => $this->id,
            'sale_id'         => $this->sale_id,
            'method'          => $this->method,
            'amount'          => $this->amount,
            'currency_code'   => $this->currency_code,
            'exchange_rate'   => $this->exchange_rate,
            'amount_original' => $this->amount_original,
            'status'          => $this->status,
            'reference'       => $this->reference,
            'confirmed_at'    => $this->confirmed_at,
            'created_at'      => $this->created_at,
            'updated_at'      => $this->updated_at,
            'sale'            => new SaleResource($this->whenLoaded('sale')),
        ];
    }
}
