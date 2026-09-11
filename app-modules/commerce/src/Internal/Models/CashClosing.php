<?php

declare(strict_types=1);

namespace Modules\Commerce\Internal\Models;

use App\Platform\Money\Casts\AmountCast;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class CashClosing extends Model
{
    use HasUuids;

    protected $fillable = [
        'cash_session_id',
        'opening_balance',
        'cash_sales',
        'mobile_money_sales',
        'expenses',
        'expected_cash',
        'declared_cash',
        'discrepancy',
    ];

    protected $casts = [
        'opening_balance'    => AmountCast::class,
        'cash_sales'         => AmountCast::class,
        'mobile_money_sales' => AmountCast::class,
        'expenses'           => AmountCast::class,
        'expected_cash'      => AmountCast::class,
        'declared_cash'      => AmountCast::class,
        'discrepancy'        => AmountCast::class,
    ];

    /** @return BelongsTo<CashSession, $this> */
    public function cashSession(): BelongsTo
    {
        return $this->belongsTo(CashSession::class, 'cash_session_id');
    }
}
