<?php

declare(strict_types=1);

namespace Modules\Commerce\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class TransferResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'                  => $this->id,
            'source_pos_id'       => $this->source_pos_id,
            'destination_pos_id'  => $this->destination_pos_id,
            'status'              => $this->status,
            'dispatched_at'       => $this->dispatched_at,
            'received_at'         => $this->received_at,
            'notes'               => $this->notes,
            'created_at'          => $this->created_at,
            'updated_at'          => $this->updated_at,
            'source_pos'          => new PointOfSaleResource($this->whenLoaded('sourcePos')),
            'destination_pos'     => new PointOfSaleResource($this->whenLoaded('destinationPos')),
            'lines'               => TransferLineResource::collection($this->whenLoaded('lines')),
        ];
    }
}
