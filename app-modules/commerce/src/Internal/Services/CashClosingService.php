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
    public function close(CashSession $session, int $declaredCash, string $closedBy): CashClosing
    {
        if (! $session->isOpen()) {
            throw new \DomainException('Cannot close a session that is not open.');
        }

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
        $expectedCash   = $openingBalance + $cashSales - $totalExpenses;
        $discrepancy    = $declaredCash - $expectedCash;

        $closing = CashClosing::create([
            'cash_session_id'    => $session->id,
            'opening_balance'    => $openingBalance,
            'cash_sales'         => $cashSales,
            'mobile_money_sales' => $mobileMoneySales,
            'expenses'           => $totalExpenses,
            'expected_cash'      => $expectedCash,
            'declared_cash'      => $declaredCash,
            'discrepancy'        => $discrepancy,
        ]);

        $session->close($closedBy, $declaredCash);

        return $closing;
    }
}
