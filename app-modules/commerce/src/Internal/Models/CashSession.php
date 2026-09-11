<?php

declare(strict_types=1);

namespace Modules\Commerce\Internal\Models;

use App\Platform\Identity\Models\User;
use App\Platform\Money\Casts\AmountCast;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Modules\Commerce\Internal\Enums\SessionState;

final class CashSession extends Model
{
    use HasUuids;

    protected $fillable = [
        'cash_register_id',
        'state',
        'opened_at',
        'closed_at',
        'opening_balance',
        'closing_balance',
        'opened_by',
        'closed_by',
    ];

    protected $casts = [
        'state'            => SessionState::class,
        'opened_at'        => 'datetime',
        'closed_at'        => 'datetime',
        'opening_balance'  => AmountCast::class,
        'closing_balance'  => AmountCast::class,
    ];

    /** @return HasOne<CashClosing, $this> */
    public function closing(): HasOne
    {
        return $this->hasOne(CashClosing::class, 'cash_session_id');
    }

    /** @return BelongsTo<CashRegister, $this> */
    public function cashRegister(): BelongsTo
    {
        return $this->belongsTo(CashRegister::class, 'cash_register_id');
    }

    /** @return BelongsTo<User, $this> */
    public function openedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'opened_by');
    }

    public function isOpen(): bool
    {
        return $this->state === SessionState::Open;
    }

    public function close(string $userId, int $closingBalance): void
    {
        if (! $this->isOpen()) {
            throw new \DomainException('Cannot close a session that is not open.');
        }

        $this->update([
            'state'           => SessionState::Closed,
            'closed_at'       => now(),
            'closing_balance' => $closingBalance,
            'closed_by'       => $userId,
        ]);
    }
}
