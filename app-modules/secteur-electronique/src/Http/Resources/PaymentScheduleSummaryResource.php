<?php

declare(strict_types=1);

namespace Modules\SecteurElectronique\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Summary shape used by PaymentScheduleController::index() — reproduces the
 * exact computed shape previously built inline in the controller's
 * serializeSchedule() helper (raw fields + total_amount / paid_amount /
 * installments_count / paid_installments_count / status /
 * next_installment_date / sale / client).
 */
final class PaymentScheduleSummaryResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $installments     = $this->installments;
        $paidInstallments = $installments->filter(fn ($i) => $i->paid_at !== null);
        $paidAmount       = $paidInstallments->sum('amount');
        $count            = $installments->count();
        $paidCount        = $paidInstallments->count();

        $now        = now();
        $hasOverdue = $installments
            ->filter(fn ($i) => $i->paid_at === null && $now->isAfter($i->due_on))
            ->isNotEmpty();

        $status = match (true) {
            $count > 0 && $paidCount === $count => 'completed',
            $hasOverdue                          => 'overdue',
            default                              => 'active',
        };

        $nextInstallment = $installments
            ->filter(fn ($i) => $i->paid_at === null)
            ->sortBy('due_on')
            ->first();

        return [
            'id'                      => $this->id,
            'sale_id'                 => $this->sale_id,
            'deposit'                 => $this->deposit,
            'total'                   => $this->total,
            'created_at'              => $this->created_at,
            'updated_at'              => $this->updated_at,
            'installments'            => InstallmentResource::collection($installments),
            'total_amount'            => $this->total,
            'paid_amount'             => $paidAmount,
            'installments_count'      => $count,
            'paid_installments_count' => $paidCount,
            'status'                  => $status,
            'next_installment_date'   => $nextInstallment?->due_on,
            'sale'                    => $this->sale ? [
                'id'     => $this->sale->id,
                'number' => $this->sale->number,
            ] : null,
            'client' => $this->sale?->customer ? [
                'id'   => $this->sale->customer->id,
                'name' => $this->sale->customer->name,
            ] : null,
        ];
    }
}
