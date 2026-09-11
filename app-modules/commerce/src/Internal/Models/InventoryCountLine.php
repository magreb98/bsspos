<?php

declare(strict_types=1);

namespace Modules\Commerce\Internal\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class InventoryCountLine extends Model
{
    use HasUuids;

    protected $fillable = [
        'inventory_count_id',
        'product_id',
        'theoretical_quantity',
        'counted_quantity',
        'adjustment',
    ];

    protected $casts = [
        'theoretical_quantity' => 'integer',
        'counted_quantity'     => 'integer',
        'adjustment'           => 'integer',
    ];

    /** @return BelongsTo<InventoryCount, $this> */
    public function inventoryCount(): BelongsTo
    {
        return $this->belongsTo(InventoryCount::class, 'inventory_count_id');
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }
}
