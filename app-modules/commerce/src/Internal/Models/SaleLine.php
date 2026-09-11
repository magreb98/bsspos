<?php

declare(strict_types=1);

namespace Modules\Commerce\Internal\Models;

use App\Platform\Money\Casts\AmountCast;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class SaleLine extends Model
{
    use HasUuids;

    protected $fillable = [
        'sale_id',
        'product_id',
        'designation',
        'unit_price',
        'vat_rate',
        'quantity',
        'line_total_excluding_tax',
        'line_total_tax',
        'line_total_including_tax',
        'allocations',
        'discount_amount',
        'coupon_id',
    ];

    protected $casts = [
        'unit_price'               => AmountCast::class,
        'vat_rate'                 => 'decimal:2',
        'quantity'                 => 'integer',
        'line_total_excluding_tax' => AmountCast::class,
        'line_total_tax'           => AmountCast::class,
        'line_total_including_tax' => AmountCast::class,
        'allocations'              => 'array',
        'discount_amount'          => AmountCast::class,
    ];

    /** @return BelongsTo<Sale, $this> */
    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class, 'sale_id');
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    /** @return BelongsTo<Coupon, $this> */
    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class, 'coupon_id');
    }
}
