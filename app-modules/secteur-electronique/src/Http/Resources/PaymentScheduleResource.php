<?php

declare(strict_types=1);

namespace Modules\SecteurElectronique\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Raw PaymentSchedule representation used by store()/show() — mirrors the
 * model's own toArray() shape exactly, including the loaded `installments`
 * relation.
 */
final class PaymentScheduleResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id'           => $this->id,
            'sale_id'      => $this->sale_id,
            'deposit'      => $this->deposit,
            'total'        => $this->total,
            'created_at'   => $this->created_at,
            'updated_at'   => $this->updated_at,
            'installments' => InstallmentResource::collection($this->whenLoaded('installments')),
        ];
    }
}
