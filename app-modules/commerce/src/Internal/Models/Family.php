<?php

declare(strict_types=1);

namespace Modules\Commerce\Internal\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class Family extends Model
{
    use HasUuids;

    protected $fillable = ['name', 'parent_id', 'active'];

    protected $casts = ['active' => 'boolean'];

    /** @return BelongsTo<Family, $this> */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Family::class, 'parent_id');
    }

    /** @return HasMany<Family, $this> */
    public function children(): HasMany
    {
        return $this->hasMany(Family::class, 'parent_id');
    }

    /** @return HasMany<Product, $this> */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class, 'family_id');
    }
}
