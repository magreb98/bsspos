<?php

declare(strict_types=1);

namespace Modules\Commerce\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class CashSessionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'                => $this->id,
            'cash_register_id'  => $this->cash_register_id,
            'state'             => $this->state,
            'opened_at'         => $this->opened_at,
            'closed_at'         => $this->closed_at,
            'opening_balance'   => $this->opening_balance,
            'closing_balance'   => $this->closing_balance,
            'closed_by'         => $this->closed_by,
            'created_at'        => $this->created_at,
            'updated_at'        => $this->updated_at,
            'cash_register'     => new CashRegisterResource($this->whenLoaded('cashRegister')),
            'opened_by'         => new MemberResource($this->whenLoaded('openedBy')),
        ];
    }
}
