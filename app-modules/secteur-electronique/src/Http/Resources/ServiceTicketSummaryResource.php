<?php

declare(strict_types=1);

namespace Modules\SecteurElectronique\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Summary shape used by ServiceTicketController::index() — reproduces the
 * exact ad-hoc array previously built inline in the controller's array_map().
 */
final class ServiceTicketSummaryResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $unit = $this->relationLoaded('serialUnit') ? $this->serialUnit : null;

        return [
            'id'             => $this->id,
            'reference'      => strtoupper(substr((string) $this->id, 0, 8)),
            'customer'       => '',
            'product'        => $unit?->product?->label ?? 'Appareil inconnu',
            'imei'           => $unit?->serial_number ?? '',
            'status'         => $this->status->value,
            'repair_cost'    => $this->repair_cost ?? 0,
            'under_warranty' => false,
            'created_at'     => $this->created_at?->toIso8601String(),
        ];
    }
}
