<?php

declare(strict_types=1);

namespace Modules\Commerce\Internal\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class Supplier extends Model
{
    use HasUuids;

    protected $fillable = [
        'name',
        'active',
    ];

    protected $casts = [
        'active' => 'boolean',
    ];

    /** @return HasMany<SupplierOrder, $this> */
    public function orders(): HasMany
    {
        return $this->hasMany(SupplierOrder::class, 'supplier_id');
    }
}
