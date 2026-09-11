<?php

declare(strict_types=1);

namespace Modules\SecteurElectronique\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Commerce\Internal\Models\Sale;

final class PaymentSchedule extends Model
{
    use HasUuids;

    protected $fillable = [
        'sale_id',
        'deposit',
        'total',
    ];

    protected $casts = [
        'deposit' => 'integer',
        'total'   => 'integer',
    ];

    /** @return BelongsTo<Sale, $this> */
    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class, 'sale_id');
    }

    /** @return HasMany<Installment, $this> */
    public function installments(): HasMany
    {
        return $this->hasMany(Installment::class, 'payment_schedule_id');
    }
}
