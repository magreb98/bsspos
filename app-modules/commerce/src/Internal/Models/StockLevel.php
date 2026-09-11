<?php

declare(strict_types=1);

namespace Modules\Commerce\Internal\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class StockLevel extends Model
{
    use HasUuids;

    protected $fillable = [
        'point_of_sale_id', 'product_id', 'product_variant_id', 'quantity', 'minimum_quantity',
    ];

    protected $casts = [
        'quantity'         => 'integer',
        'minimum_quantity' => 'integer',
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

    /** @return BelongsTo<ProductVariant, $this> */
    public function productVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function isAvailable(): bool
    {
        return $this->quantity > 0;
    }
}
