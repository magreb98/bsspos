<?php

declare(strict_types=1);

namespace Modules\Commerce\Internal\Services;

use Modules\Commerce\Internal\Enums\PaymentMethod;
use Modules\Commerce\Internal\Enums\PaymentStatus;
use Modules\Commerce\Internal\Models\CashClosing;
use Modules\Commerce\Internal\Models\CashSession;
use Modules\Commerce\Internal\Models\Expense;
use Modules\Commerce\Internal\Models\Payment;
use Modules\Commerce\Internal\Models\Sale;

final class CashClosingService
{
    /**
     * Live cash position for an open session — same formula used at actual
     * closing time (see close()), exposed early so the "Session en cours"
     * screen can show a number that means something before the drawer is
     * counted, instead of ignoring the day's cash sales entirely.
     *
     * @return array{opening_balance: int, cash_sales: int, mobile_money_sales: int, expenses: int, expected_cash: int}
     */
    public function computeExpected(CashSession $session): array
    {
        $saleIds = Sale::where('cash_session_id', $session->id)->pluck('id');

        $cashSales = (int) Payment::whereIn('sale_id', $saleIds)
            ->where('method', PaymentMethod::Cash)
            ->where('status', PaymentStatus::Confirmed)
            ->sum('amount');

        $mobileMoneySales = (int) Payment::whereIn('sale_id', $saleIds)
            ->where('method', PaymentMethod::MobileMoney)
            ->where('status', PaymentStatus::Confirmed)
            ->sum('amount');

        $totalExpenses = (int) Expense::where('cash_session_id', $session->id)->sum('amount');

        $openingBalance = $session->opening_balance?->toInt() ?? 0;

        return [
            'opening_balance'    => $openingBalance,
            'cash_sales'         => $cashSales,
            'mobile_money_sales' => $mobileMoneySales,
            'expenses'           => $totalExpenses,
            'expected_cash'      => $openingBalance + $cashSales - $totalExpenses,
        ];
    }

    public function close(CashSession $session, int $declaredCash, string $closedBy): CashClosing
    {
        if (! $session->isOpen()) {
            throw new \DomainException('Cannot close a session that is not open.');
        }

        $computed = $this->computeExpected($session);
        $discrepancy = $declaredCash - $computed['expected_cash'];

        $closing = CashClosing::create([
            'cash_session_id'    => $session->id,
            'opening_balance'    => $computed['opening_balance'],
            'cash_sales'         => $computed['cash_sales'],
            'mobile_money_sales' => $computed['mobile_money_sales'],
            'expenses'           => $computed['expenses'],
            'expected_cash'      => $computed['expected_cash'],
            'declared_cash'      => $declaredCash,
            'discrepancy'        => $discrepancy,
        ]);

        $session->close($closedBy, $declaredCash);

        return $closing;
    }
}
