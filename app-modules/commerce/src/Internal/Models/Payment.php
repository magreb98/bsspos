<?php

declare(strict_types=1);

namespace Modules\Commerce\Internal\Models;

use App\Platform\Money\Casts\AmountCast;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Commerce\Internal\Casts\FlexPaymentMethodCast;
use Modules\Commerce\Internal\Enums\PaymentStatus;

final class Payment extends Model
{
    use HasUuids;

    protected $fillable = [
        'sale_id',
        'method',
        'amount',
        'currency_code',
        'exchange_rate',
        'amount_original',
        'status',
        'reference',
        'confirmed_at',
    ];

    protected $casts = [
        'method'          => FlexPaymentMethodCast::class,
        'amount'          => AmountCast::class,
        'exchange_rate'   => 'integer',
        'amount_original' => 'integer',
        'status'          => PaymentStatus::class,
        'confirmed_at'    => 'datetime',
    ];

    /** @return BelongsTo<Sale, $this> */
    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class, 'sale_id');
    }
}
