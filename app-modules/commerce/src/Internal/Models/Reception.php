<?php

declare(strict_types=1);

namespace Modules\Commerce\Internal\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class Reception extends Model
{
    use HasUuids;

    protected $fillable = [
        'supplier_order_id',
        'point_of_sale_id',
        'received_at',
        'notes',
    ];

    protected $casts = [
        'received_at' => 'datetime',
    ];

    /** @return BelongsTo<SupplierOrder, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(SupplierOrder::class, 'supplier_order_id');
    }

    /** @return BelongsTo<PointOfSale, $this> */
    public function pointOfSale(): BelongsTo
    {
        return $this->belongsTo(PointOfSale::class, 'point_of_sale_id');
    }

    /** @return HasMany<ReceptionLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(ReceptionLine::class, 'reception_id');
    }
}
