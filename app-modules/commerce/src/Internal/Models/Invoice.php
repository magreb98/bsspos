<?php

declare(strict_types=1);

namespace Modules\Commerce\Internal\Models;

use App\Platform\Money\Casts\AmountCast;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Commerce\Internal\Enums\InvoiceStatus;

final class Invoice extends Model
{
    use HasUuids;

    protected $fillable = [
        'number_sequence',
        'number',
        'status',
        'client_id',
        'sale_id',
        'issue_date',
        'due_date',
        'total_ht',
        'total_vat',
        'total_ttc',
        'paid_amount',
        'outstanding_amount',
        'notes',
    ];

    protected $casts = [
        'status'             => InvoiceStatus::class,
        'issue_date'         => 'date',
        'due_date'           => 'date',
        'number_sequence'    => 'integer',
        'total_ht'           => AmountCast::class,
        'total_vat'          => AmountCast::class,
        'total_ttc'          => AmountCast::class,
        'paid_amount'        => AmountCast::class,
        'outstanding_amount' => AmountCast::class,
    ];

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'client_id');
    }

    /** @return BelongsTo<Sale, $this> */
    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    /** @return HasMany<InvoicePayment, $this> */
    public function payments(): HasMany
    {
        return $this->hasMany(InvoicePayment::class);
    }

    public function isFullyPaid(): bool
    {
        return ($this->outstanding_amount?->toInt() ?? 0) <= 0;
    }

    public function isOverdue(): bool
    {
        return $this->due_date !== null
            && $this->due_date->isPast()
            && ! $this->isFullyPaid();
    }
}
