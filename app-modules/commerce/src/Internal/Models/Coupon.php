<?php

declare(strict_types=1);

namespace Modules\Commerce\Internal\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class Coupon extends Model
{
    use HasUuids;

    protected $fillable = [
        'code',
        'promotion_id',
        'max_uses',
        'times_used',
    ];

    protected $casts = [
        'max_uses'   => 'integer',
        'times_used' => 'integer',
    ];

    /** @return BelongsTo<Promotion, $this> */
    public function promotion(): BelongsTo
    {
        return $this->belongsTo(Promotion::class, 'promotion_id');
    }

    public function isExhausted(): bool
    {
        return $this->max_uses !== null && $this->times_used >= $this->max_uses;
    }
}
