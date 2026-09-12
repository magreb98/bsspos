<?php

declare(strict_types=1);

namespace Modules\Commerce\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class CashClosingResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'                  => $this->id,
            'cash_session_id'     => $this->cash_session_id,
            'opening_balance'     => $this->opening_balance,
            'cash_sales'          => $this->cash_sales,
            'mobile_money_sales'  => $this->mobile_money_sales,
            'expenses'            => $this->expenses,
            'expected_cash'       => $this->expected_cash,
            'declared_cash'       => $this->declared_cash,
            'discrepancy'         => $this->discrepancy,
            'created_at'          => $this->created_at,
            'updated_at'          => $this->updated_at,
            'cashSession'         => new CashSessionResource($this->whenLoaded('cashSession')),
        ];
    }
}
