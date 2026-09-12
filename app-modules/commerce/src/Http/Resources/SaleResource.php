<?php

declare(strict_types=1);

namespace Modules\Commerce\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class SaleResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'                   => $this->id,
            'cash_session_id'      => $this->cash_session_id,
            'client_id'            => $this->client_id,
            'return_of_sale_id'    => $this->return_of_sale_id,
            'state'                => $this->state,
            'number'               => $this->number,
            'total_excluding_tax'  => $this->total_excluding_tax,
            'total_tax'            => $this->total_tax,
            'total_including_tax'  => $this->total_including_tax,
            'idempotency_key'      => $this->idempotency_key,
            'anomaly'              => $this->anomaly,
            'confirmed_at'         => $this->confirmed_at,
            'loyalty_points_used'  => $this->loyalty_points_used,
            'created_at'           => $this->created_at,
            'updated_at'           => $this->updated_at,
            'lines_count'          => $this->whenCounted('lines'),
            'customer'             => new CustomerResource($this->whenLoaded('customer')),
            'cashSession'          => new CashSessionResource($this->whenLoaded('cashSession')),
            'lines'                => SaleLineResource::collection($this->whenLoaded('lines')),
            'payments'             => PaymentResource::collection($this->whenLoaded('payments')),
            'originalSale'         => new SaleResource($this->whenLoaded('originalSale')),
            'returnSale'           => new SaleResource($this->whenLoaded('returnSale')),
        ];
    }
}
