<?php

declare(strict_types=1);

namespace Modules\SecteurElectronique\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Raw ServiceTicket representation used by store()/show()/update() —
 * mirrors the model's own toArray() shape exactly.
 */
final class ServiceTicketResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id'             => $this->id,
            'serial_unit_id' => $this->serial_unit_id,
            'status'         => $this->status,
            'description'    => $this->description,
            'repair_cost'    => $this->repair_cost,
            'opened_at'      => $this->opened_at,
            'closed_at'      => $this->closed_at,
            'created_at'     => $this->created_at,
            'updated_at'     => $this->updated_at,
        ];
    }
}
