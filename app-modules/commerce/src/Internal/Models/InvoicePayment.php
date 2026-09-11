<?php

declare(strict_types=1);

namespace Modules\Commerce\Internal\Models;

use App\Platform\Money\Casts\AmountCast;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class InvoicePayment extends Model
{
    use HasUuids;

    protected $fillable = [
        'invoice_id',
        'amount',
        'payment_date',
        'method',
        'reference',
    ];

    protected $casts = [
        'amount'       => AmountCast::class,
        'payment_date' => 'date',
    ];

    /** @return BelongsTo<Invoice, $this> */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }
}
