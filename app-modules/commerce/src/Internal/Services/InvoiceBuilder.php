<?php

declare(strict_types=1);

namespace Modules\Commerce\Internal\Services;

use Modules\Commerce\Internal\Documents\InvoiceData;
use Modules\Commerce\Internal\Documents\InvoiceLineData;
use Modules\Commerce\Internal\Enums\SaleState;
use Modules\Commerce\Internal\Models\InvoiceSetting;
use Modules\Commerce\Internal\Models\Sale;

final class InvoiceBuilder
{
    public function build(Sale $sale): InvoiceData
    {
        if ($sale->state !== SaleState::Confirmed) {
            throw new \DomainException('Cannot build an invoice for a non-confirmed sale.');
        }

        $settings = InvoiceSetting::firstOrFail();
        $lines    = $sale->lines()->get();

        $invoiceLines = array_values($lines->map(fn ($line) => new InvoiceLineData(
            designation: $line->designation,
            unitPrice:   $line->unit_price?->toInt() ?? 0,
            quantity:    $line->quantity,
            lineTotal:   $line->line_total_including_tax?->toInt() ?? 0,
        ))->all());

        return new InvoiceData(
            invoiceNumber:     (string) $sale->number,
            niu:               $settings->niu,
            rccm:              $settings->rccm,
            companyName:       $settings->company_name,
            totalExcludingTax: $sale->total_excluding_tax?->toInt() ?? 0,
            totalTax:          $sale->total_tax?->toInt() ?? 0,
            totalIncludingTax: $sale->total_including_tax?->toInt() ?? 0,
            vatRate:           '19.25',
            lines:             $invoiceLines,
            confirmedAt:       $sale->confirmed_at ?? throw new \DomainException('Sale not confirmed.'),
        );
    }
}
