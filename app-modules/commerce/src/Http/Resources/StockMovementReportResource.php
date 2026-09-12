<?php

declare(strict_types=1);

namespace Modules\Commerce\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Commerce\Internal\Models\StockMovement;

/**
 * @property StockMovement $resource
 */
final class StockMovementReportResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var StockMovement $m */
        $m = $this->resource;

        return [
            'id'         => $m->id,
            'product'    => $m->product?->label ?? $m->product_id,
            'type'       => $m->quantity >= 0 ? 'entree' : 'sortie',
            'qty'        => $m->quantity,
            'reason'     => $m->sale_line_id !== null ? 'Vente' : 'Ajustement',
            'created_at' => $m->occurred_at?->toIso8601String() ?? $m->created_at?->toIso8601String(),
        ];
    }
}
