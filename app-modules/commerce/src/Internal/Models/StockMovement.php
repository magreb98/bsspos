<?php

declare(strict_types=1);

namespace Modules\Commerce\Internal\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class StockMovement extends Model
{
    use HasUuids;

    protected $fillable = [
        'point_of_sale_id',
        'product_id',
        'sale_line_id',
        'quantity',
        'occurred_at',
    ];

    protected $casts = [
        'quantity'    => 'integer',
        'occurred_at' => 'datetime',
    ];

    /** @return BelongsTo<PointOfSale, $this> */
    public function pointOfSale(): BelongsTo
    {
        return $this->belongsTo(PointOfSale::class, 'point_of_sale_id');
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    /** @return BelongsTo<SaleLine, $this> */
    public function saleLine(): BelongsTo
    {
        return $this->belongsTo(SaleLine::class, 'sale_line_id');
    }
}
