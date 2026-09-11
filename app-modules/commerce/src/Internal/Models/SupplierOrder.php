<?php

declare(strict_types=1);

namespace Modules\Commerce\Internal\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Commerce\Internal\Enums\SupplierOrderStatus;

final class SupplierOrder extends Model
{
    use HasUuids;

    protected $fillable = [
        'supplier_id',
        'status',
        'ordered_at',
    ];

    protected $casts = [
        'status'     => SupplierOrderStatus::class,
        'ordered_at' => 'datetime',
    ];

    /** @return BelongsTo<Supplier, $this> */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'supplier_id');
    }

    /** @return HasMany<SupplierOrderLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(SupplierOrderLine::class, 'supplier_order_id');
    }

    /** @return HasMany<Reception, $this> */
    public function receptions(): HasMany
    {
        return $this->hasMany(Reception::class, 'supplier_order_id');
    }
}
