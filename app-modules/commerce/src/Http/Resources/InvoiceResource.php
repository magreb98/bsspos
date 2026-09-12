<?php

declare(strict_types=1);

namespace Modules\Commerce\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class InvoiceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'                 => $this->id,
            'number_sequence'    => $this->number_sequence,
            'number'             => $this->number,
            'status'             => $this->status,
            'client_id'          => $this->client_id,
            'sale_id'            => $this->sale_id,
            'issue_date'         => $this->issue_date,
            'due_date'           => $this->due_date,
            'total_ht'           => $this->total_ht,
            'total_vat'          => $this->total_vat,
            'total_ttc'          => $this->total_ttc,
            'paid_amount'        => $this->paid_amount,
            'outstanding_amount' => $this->outstanding_amount,
            'notes'              => $this->notes,
            'created_at'         => $this->created_at,
            'updated_at'         => $this->updated_at,
            'customer'           => new CustomerResource($this->whenLoaded('customer')),
            'sale'               => new SaleResource($this->whenLoaded('sale')),
            'payments'           => InvoicePaymentResource::collection($this->whenLoaded('payments')),
        ];
    }
}
