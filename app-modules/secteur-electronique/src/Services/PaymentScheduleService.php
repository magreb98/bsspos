<?php

declare(strict_types=1);

namespace Modules\SecteurElectronique\Services;

use Modules\Commerce\Contracts\InstallmentBuilderContract;
use Modules\Commerce\Internal\Models\Sale;
use Modules\SecteurElectronique\Models\Installment;
use Modules\SecteurElectronique\Models\PaymentSchedule;

final class PaymentScheduleService implements InstallmentBuilderContract
{
    /**
     * InstallmentBuilderContract — delegates to create(), discards the returned schedule.
     *
     * @param list<array{amount: int, due_on: string}> $installmentData
     * @throws \DomainException if INV-06 is violated
     */
    public function build(Sale $sale, int $deposit, array $installmentData): void
    {
        $this->create($sale, $deposit, $installmentData);
    }

    /**
     * Create a payment schedule for a sale, enforcing INV-06.
     *
     * @param list<array{amount: int, due_on: string}> $installmentData
     * @throws \DomainException if deposit + sum(installments) ≠ sale total
     */
    public function create(Sale $sale, int $deposit, array $installmentData): PaymentSchedule
    {
        $total        = $sale->total_including_tax?->toInt() ?? 0;
        $installTotal = array_sum(array_column($installmentData, 'amount'));

        if ($deposit + $installTotal !== $total) {
            throw new \DomainException(
                "INV-06 violated: deposit ({$deposit}) + installments ({$installTotal}) ≠ total ({$total})."
            );
        }

        $schedule = PaymentSchedule::create([
            'sale_id' => $sale->id,
            'deposit' => $deposit,
            'total'   => $total,
        ]);

        foreach ($installmentData as $data) {
            Installment::create([
                'payment_schedule_id' => $schedule->id,
                'amount'              => $data['amount'],
                'due_on'              => $data['due_on'],
            ]);
        }

        return $schedule;
    }

    public function verify(PaymentSchedule $schedule): bool
    {
        $installTotal = Installment::where('payment_schedule_id', $schedule->id)->sum('amount');

        return $schedule->deposit + (int) $installTotal === $schedule->total;
    }

    public function payInstallment(Installment $installment): void
    {
        $installment->update(['paid_at' => now()]);
    }
}
