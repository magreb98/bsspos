<?php

declare(strict_types=1);

namespace Modules\Commerce\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class ExpenseResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'               => $this->id,
            'cash_session_id'  => $this->cash_session_id,
            'amount'           => $this->amount,
            'label'            => $this->label,
            'recorded_at'      => $this->recorded_at,
            'created_at'       => $this->created_at,
            'updated_at'       => $this->updated_at,
            'cashSession'      => new CashSessionResource($this->whenLoaded('cashSession')),
        ];
    }
}
