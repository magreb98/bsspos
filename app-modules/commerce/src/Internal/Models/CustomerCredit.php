<?php

declare(strict_types=1);

namespace Modules\Commerce\Internal\Models;

use App\Platform\Money\Casts\AmountCast;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class CustomerCredit extends Model
{
    use HasUuids;

    protected $fillable = [
        'customer_id',
        'return_sale_id',
        'original_amount',
        'remaining_amount',
    ];

    protected $casts = [
        'original_amount'  => AmountCast::class,
        'remaining_amount' => AmountCast::class,
    ];

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    /** @return BelongsTo<Sale, $this> */
    public function returnSale(): BelongsTo
    {
        return $this->belongsTo(Sale::class, 'return_sale_id');
    }

    public function isExhausted(): bool
    {
        return ($this->remaining_amount?->toInt() ?? 0) <= 0;
    }
}
