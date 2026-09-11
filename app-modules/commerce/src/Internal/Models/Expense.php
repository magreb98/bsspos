<?php

declare(strict_types=1);

namespace Modules\Commerce\Internal\Models;

use App\Platform\Money\Casts\AmountCast;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class Expense extends Model
{
    use HasUuids;

    protected $fillable = [
        'cash_session_id',
        'amount',
        'label',
        'recorded_at',
    ];

    protected $casts = [
        'amount'      => AmountCast::class,
        'recorded_at' => 'datetime',
    ];

    /** @return BelongsTo<CashSession, $this> */
    public function cashSession(): BelongsTo
    {
        return $this->belongsTo(CashSession::class, 'cash_session_id');
    }
}
