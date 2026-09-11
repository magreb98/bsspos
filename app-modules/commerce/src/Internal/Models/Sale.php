<?php

declare(strict_types=1);

namespace Modules\Commerce\Internal\Models;

use App\Platform\Money\Casts\AmountCast;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Modules\Commerce\Internal\Enums\SaleState;

final class Sale extends Model
{
    use HasUuids;

    protected $fillable = [
        'cash_session_id',
        'client_id',
        'return_of_sale_id',
        'state',
        'number',
        'total_excluding_tax',
        'total_tax',
        'total_including_tax',
        'idempotency_key',
        'anomaly',
        'confirmed_at',
        'valid_until',
        'loyalty_points_used',
    ];

    protected $casts = [
        'state'                => SaleState::class,
        'valid_until'          => 'date:Y-m-d',
        'total_excluding_tax'  => AmountCast::class,
        'total_tax'            => AmountCast::class,
        'total_including_tax'  => AmountCast::class,
        'confirmed_at'         => 'datetime',
        'loyalty_points_used'  => 'integer',
    ];

    /** @return BelongsTo<CashSession, $this> */
    public function cashSession(): BelongsTo
    {
        return $this->belongsTo(CashSession::class, 'cash_session_id');
    }

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'client_id');
    }

    /** @return HasMany<SaleLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(SaleLine::class, 'sale_id');
    }

    /** @return HasMany<Payment, $this> */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class, 'sale_id');
    }

    /** @return BelongsTo<self, $this> */
    public function originalSale(): BelongsTo
    {
        return $this->belongsTo(self::class, 'return_of_sale_id');
    }

    /** @return HasOne<self, $this> */
    public function returnSale(): HasOne
    {
        return $this->hasOne(self::class, 'return_of_sale_id');
    }

    public function isConfirmed(): bool
    {
        return $this->state === SaleState::Confirmed;
    }
}
