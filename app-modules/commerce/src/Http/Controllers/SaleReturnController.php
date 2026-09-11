<?php

declare(strict_types=1);

namespace Modules\Commerce\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Commerce\Internal\Enums\Granularity;
use Modules\Commerce\Internal\Enums\SaleState;
use Modules\Commerce\Internal\Models\CustomerCredit;
use Modules\Commerce\Internal\Models\Sale;
use Modules\Commerce\Internal\Models\SaleLine;
use Modules\Commerce\Internal\Models\StockMovement;

final class SaleReturnController
{
    public function store(Request $request, Sale $sale): JsonResponse
    {
        if (! $sale->isConfirmed()) {
            return response()->json(['code' => 'SALE_NOT_CONFIRMED', 'message' => 'Seule une vente confirmée peut faire l\'objet d\'un retour.', 'champ' => null], 409);
        }

        if ($sale->returnSale()->exists()) {
            return response()->json(['code' => 'RETURN_ALREADY_EXISTS', 'message' => 'Un retour existe déjà pour cette vente.', 'champ' => null], 409);
        }

        $validated = $request->validate([
            'lines'                   => ['sometimes', 'array'],
            'lines.*.sale_line_id'    => ['required_with:lines', 'uuid'],
            'lines.*.quantity'        => ['required_with:lines', 'integer', 'min:1'],
        ]);

        $originalLines = $sale->lines()->with('product')->get()->keyBy('id');

        // Determine which lines to return (all if not specified)
        if (empty($validated['lines'])) {
            $returnLines = $originalLines->map(static fn (SaleLine $l): array => [
                'line'     => $l,
                'quantity' => $l->quantity,
            ])->values()->all();
        } else {
            $returnLines = [];

            foreach ($validated['lines'] as $item) {
                $line = $originalLines->get($item['sale_line_id']);

                if ($line === null) {
                    return response()->json(['code' => 'LINE_NOT_FOUND', 'message' => "Ligne {$item['sale_line_id']} introuvable sur cette vente.", 'champ' => 'lines'], 422);
                }

                if ((int) $item['quantity'] > $line->quantity) {
                    return response()->json(['code' => 'QUANTITY_EXCEEDS_ORIGINAL', 'message' => 'La quantité retournée dépasse la quantité vendue.', 'champ' => 'lines'], 422);
                }

                $returnLines[] = ['line' => $line, 'quantity' => (int) $item['quantity']];
            }
        }

        $returnSale = DB::transaction(function () use ($sale, $returnLines): Sale {
            $session = $sale->cashSession;

            $ret = Sale::create([
                'cash_session_id'   => $sale->cash_session_id,
                'client_id'         => $sale->client_id,
                'return_of_sale_id' => $sale->id,
                'state'             => SaleState::Confirmed,
                'idempotency_key'   => (string) Str::uuid(),
                'confirmed_at'      => now(),
            ]);

            $totalHt  = 0;
            $totalTax = 0;
            $totalTtc = 0;

            foreach ($returnLines as ['line' => $line, 'quantity' => $qty]) {
                $ratio = $qty / max(1, $line->quantity);

                $lineHt  = -(int) round(($line->line_total_excluding_tax?->toInt() ?? 0) * $ratio);
                $lineTax = -(int) round(($line->line_total_tax?->toInt() ?? 0) * $ratio);
                $lineTtc = -(int) round(($line->line_total_including_tax?->toInt() ?? 0) * $ratio);

                SaleLine::create([
                    'sale_id'                  => $ret->id,
                    'product_id'               => $line->product_id,
                    'designation'              => $line->designation,
                    'unit_price'               => $line->unit_price?->toInt() ?? 0,
                    'vat_rate'                 => $line->vat_rate,
                    'quantity'                 => -$qty,
                    'line_total_excluding_tax' => $lineHt,
                    'line_total_tax'           => $lineTax,
                    'line_total_including_tax' => $lineTtc,
                    'allocations'              => [],
                ]);

                $totalHt  += $lineHt;
                $totalTax += $lineTax;
                $totalTtc += $lineTtc;

                // Add stock back (positive movement) for non-service products
                if ($line->product !== null && $line->product->granularity !== Granularity::Service) {
                    $pos = $session?->cashRegister?->pointOfSale;

                    if ($pos !== null) {
                        StockMovement::create([
                            'point_of_sale_id' => $pos->id,
                            'product_id'       => $line->product_id,
                            'sale_line_id'     => null,
                            'quantity'         => $qty,
                            'occurred_at'      => now(),
                        ]);
                    }
                }
            }

            $year   = now()->year;
            $count  = Sale::whereNotNull('return_of_sale_id')->whereYear('created_at', $year)->count();
            $number = sprintf('R-%d-%04d', $year, $count);

            $ret->update([
                'number'              => $number,
                'total_excluding_tax' => $totalHt,
                'total_tax'           => $totalTax,
                'total_including_tax' => $totalTtc,
            ]);

            // Issue a credit note if the original sale had a customer
            if ($sale->client_id !== null && $totalTtc < 0) {
                CustomerCredit::create([
                    'customer_id'      => $sale->client_id,
                    'return_sale_id'   => $ret->id,
                    'original_amount'  => abs($totalTtc),
                    'remaining_amount' => abs($totalTtc),
                ]);
            }

            return $ret;
        });

        return response()->json(['data' => $returnSale->fresh()?->load('lines')->toArray()], 201);
    }
}
