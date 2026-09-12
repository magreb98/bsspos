<?php

declare(strict_types=1);

namespace Modules\Commerce\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Commerce\Internal\Models\InvoiceSetting;
use Modules\Commerce\Internal\Models\Sale;
use Modules\Commerce\Internal\Models\SaleLine;

final class SaleReceiptResource extends JsonResource
{
    public function __construct(Sale $resource, private readonly ?InvoiceSetting $setting)
    {
        parent::__construct($resource);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Sale $sale */
        $sale = $this->resource;

        $lines = $sale->lines->map(static fn (SaleLine $l): array => [
            'designation'              => $l->designation,
            'quantity'                 => $l->quantity,
            'unit_price'               => $l->unit_price?->toInt() ?? 0,
            'vat_rate'                 => (string) $l->vat_rate,
            'line_total_excluding_tax' => $l->line_total_excluding_tax?->toInt() ?? 0,
            'line_total_tax'           => $l->line_total_tax?->toInt() ?? 0,
            'line_total_including_tax' => $l->line_total_including_tax?->toInt() ?? 0,
            'discount_amount'          => $l->discount_amount?->toInt() ?? 0,
        ]);

        $payments = $sale->payments->map(static fn ($p): array => [
            'method'       => $p->method instanceof \BackedEnum ? $p->method->value : $p->method,
            'amount'       => $p->amount?->toInt() ?? 0,
            'status'       => $p->status->value,
            'reference'    => $p->reference,
            'confirmed_at' => $p->confirmed_at?->toIso8601String(),
        ]);

        return [
            'company' => $this->setting !== null ? [
                'name'    => $this->setting->company_name,
                'niu'     => $this->setting->niu,
                'rccm'    => $this->setting->rccm,
                'address' => $this->setting->address,
                'phone'   => $this->setting->phone,
            ] : null,
            'sale' => [
                'id'                  => $sale->id,
                'number'              => $sale->number,
                'state'               => $sale->state->value,
                'confirmed_at'        => $sale->confirmed_at?->toIso8601String(),
                'total_excluding_tax' => $sale->total_excluding_tax?->toInt() ?? 0,
                'total_tax'           => $sale->total_tax?->toInt() ?? 0,
                'total_including_tax' => $sale->total_including_tax?->toInt() ?? 0,
            ],
            'customer' => $sale->customer?->toArray(),
            'lines'    => $lines->all(),
            'payments' => $payments->all(),
        ];
    }
}
