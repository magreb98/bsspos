<?php

declare(strict_types=1);

namespace Modules\Commerce\Internal\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class InventoryCount extends Model
{
    use HasUuids;

    protected $fillable = [
        'point_of_sale_id',
        'counted_at',
        'notes',
    ];

    protected $casts = [
        'counted_at' => 'datetime',
    ];

    /** @return BelongsTo<PointOfSale, $this> */
    public function pointOfSale(): BelongsTo
    {
        return $this->belongsTo(PointOfSale::class, 'point_of_sale_id');
    }

    /** @return HasMany<InventoryCountLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(InventoryCountLine::class, 'inventory_count_id');
    }
}
