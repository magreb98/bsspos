<?php

declare(strict_types=1);

namespace Modules\Commerce\Internal\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Commerce\Internal\Services\PerimeterLinker;

final class OrganizationalUnit extends Model
{
    use HasUuids;

    protected $fillable = ['name', 'parent_id', 'active', 'perimeter_id'];

    protected static function booted(): void
    {
        static::created(function (OrganizationalUnit $unit): void {
            (new PerimeterLinker())->link($unit);
        });
    }

    protected $casts = ['active' => 'boolean'];

    /** @return BelongsTo<OrganizationalUnit, $this> */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(OrganizationalUnit::class, 'parent_id');
    }

    /** @return HasMany<OrganizationalUnit, $this> */
    public function children(): HasMany
    {
        return $this->hasMany(OrganizationalUnit::class, 'parent_id');
    }

    /** @return HasMany<PointOfSale, $this> */
    public function pointsOfSale(): HasMany
    {
        return $this->hasMany(PointOfSale::class, 'organizational_unit_id');
    }
}
