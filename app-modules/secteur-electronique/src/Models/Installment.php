<?php

declare(strict_types=1);

namespace Modules\SecteurElectronique\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class Installment extends Model
{
    use HasUuids;

    protected $fillable = [
        'payment_schedule_id',
        'amount',
        'due_on',
        'paid_at',
    ];

    protected $casts = [
        'amount'  => 'integer',
        'due_on'  => 'string',
        'paid_at' => 'datetime',
    ];

    /** @return BelongsTo<PaymentSchedule, $this> */
    public function paymentSchedule(): BelongsTo
    {
        return $this->belongsTo(PaymentSchedule::class, 'payment_schedule_id');
    }
}
