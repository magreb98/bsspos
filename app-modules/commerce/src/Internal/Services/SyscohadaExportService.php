<?php

declare(strict_types=1);

namespace Modules\Commerce\Internal\Services;

use Modules\Commerce\Internal\Enums\SaleState;
use Modules\Commerce\Internal\Models\JournalEntry;
use Modules\Commerce\Internal\Models\Sale;

final class SyscohadaExportService
{
    /**
     * Emit SYSCOHADA journal entries for a confirmed sale.
     * Idempotent: replaying with the same sale returns existing entries unchanged.
     *
     * @return list<JournalEntry>
     * @throws \DomainException if the sale is not confirmed
     */
    public function emitForSale(Sale $sale): array
    {
        if ($sale->state !== SaleState::Confirmed) {
            throw new \DomainException(
                "Sale '{$sale->id}' must be confirmed before SYSCOHADA export."
            );
        }

        $entryDate = $sale->confirmed_at?->toDateString() ?? now()->toDateString();
        $reference = (string) ($sale->number ?? $sale->id);
        $emittedAt = now();

        $netHt   = $sale->total_excluding_tax?->toInt() ?? 0;
        $tax     = $sale->total_tax?->toInt() ?? 0;
        $ttc     = $sale->total_including_tax?->toInt() ?? 0;
        $discount = (int) $sale->lines()->sum('discount_amount');
        $grossHt  = $netHt + $discount;

        $lines = [
            [
                'account_number' => '571',
                'label'          => "Vente {$reference}",
                'debit'          => $ttc,
                'credit'         => 0,
            ],
            [
                'account_number' => '701',
                'label'          => "Vente {$reference}",
                'debit'          => 0,
                'credit'         => $grossHt,
            ],
            [
                'account_number' => '443',
                'label'          => "TVA sur vente {$reference}",
                'debit'          => 0,
                'credit'         => $tax,
            ],
        ];

        if ($discount > 0) {
            $lines[] = [
                'account_number' => '709',
                'label'          => "Remise sur vente {$reference}",
                'debit'          => $discount,
                'credit'         => 0,
            ];
        }

        $entries = [];

        foreach ($lines as $line) {
            $key = "sale:{$sale->id}:{$line['account_number']}";

            $entry = JournalEntry::where('idempotency_key', $key)->first();

            if ($entry === null) {
                $entry = JournalEntry::create([
                    'idempotency_key' => $key,
                    'reference'       => $reference,
                    'source_type'     => 'sale',
                    'source_id'       => $sale->id,
                    'journal_code'    => 'VTE',
                    'account_number'  => $line['account_number'],
                    'label'           => $line['label'],
                    'debit'           => $line['debit'],
                    'credit'          => $line['credit'],
                    'entry_date'      => $entryDate,
                    'emitted_at'      => $emittedAt,
                ]);
            }

            $entries[] = $entry;
        }

        return $entries;
    }
}
