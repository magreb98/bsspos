<?php

declare(strict_types=1);

namespace Modules\SecteurElectronique\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class InstallmentResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id'                   => $this->id,
            'payment_schedule_id'  => $this->payment_schedule_id,
            'amount'               => $this->amount,
            'due_on'               => $this->due_on,
            'paid_at'              => $this->paid_at,
            'created_at'           => $this->created_at,
            'updated_at'           => $this->updated_at,
        ];
    }
}
