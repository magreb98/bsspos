<?php

declare(strict_types=1);

namespace Modules\Commerce\Internal\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Commerce\Internal\Enums\PromotionScope;
use Modules\Commerce\Internal\Enums\PromotionType;

final class Promotion extends Model
{
    use HasUuids;

    protected $fillable = [
        'name',
        'type',
        'value',
        'scope',
        'scope_id',
        'starts_at',
        'ends_at',
        'cumulative',
        'active',
    ];

    protected $casts = [
        'type'       => PromotionType::class,
        'value'      => 'integer',
        'scope'      => PromotionScope::class,
        'starts_at'  => 'datetime',
        'ends_at'    => 'datetime',
        'cumulative' => 'boolean',
        'active'     => 'boolean',
    ];

    /** @return HasMany<Coupon, $this> */
    public function coupons(): HasMany
    {
        return $this->hasMany(Coupon::class, 'promotion_id');
    }

    public function isActiveAt(\DateTimeInterface $at): bool
    {
        if (! $this->active) {
            return false;
        }

        if ($this->starts_at !== null && $this->starts_at > $at) {
            return false;
        }

        if ($this->ends_at !== null && $this->ends_at < $at) {
            return false;
        }

        return true;
    }
}
