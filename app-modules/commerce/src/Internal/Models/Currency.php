<?php

declare(strict_types=1);

namespace Modules\Commerce\Internal\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

final class Currency extends Model
{
    use HasUuids;

    protected $fillable = [
        'code',
        'name',
        'symbol',
        'exchange_rate',
        'is_base',
        'active',
    ];

    protected $casts = [
        'exchange_rate' => 'integer',
        'is_base'       => 'boolean',
        'active'        => 'boolean',
    ];

    /**
     * Convert a foreign currency amount to XAF.
     * exchange_rate is stored as actual_rate × 1000.
     * e.g. exchange_rate = 655000 → 1 USD = 655 XAF
     */
    public function toXaf(int $amount): int
    {
        return intdiv($amount * $this->exchange_rate + 500, 1000);
    }
}
