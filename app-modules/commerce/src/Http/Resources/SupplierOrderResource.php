<?php

declare(strict_types=1);

namespace Modules\Commerce\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class SupplierOrderResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'supplier_id' => $this->supplier_id,
            'status'      => $this->status,
            'ordered_at'  => $this->ordered_at,
            'created_at'  => $this->created_at,
            'updated_at'  => $this->updated_at,
            'supplier'    => new SupplierResource($this->whenLoaded('supplier')),
            'lines'       => SupplierOrderLineResource::collection($this->whenLoaded('lines')),
            'receptions'  => ReceptionResource::collection($this->whenLoaded('receptions')),
        ];
    }
}
