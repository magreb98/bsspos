<?php

declare(strict_types=1);

namespace Modules\Commerce\Internal\Models;

use App\Platform\Money\Amount;
use App\Platform\Money\Casts\AmountCast;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

final class Customer extends Model
{
    use HasUuids;

    protected $fillable = [
        'name', 'phone', 'outstanding_balance', 'credit_limit', 'loyalty_points', 'active',
    ];

    protected $casts = [
        'outstanding_balance' => AmountCast::class,
        'credit_limit'        => AmountCast::class,
        'loyalty_points'      => 'integer',
        'active'              => 'boolean',
    ];

    public function exceedsLimit(): bool
    {
        /** @var Amount $balance */
        $balance = $this->outstanding_balance ?? Amount::fromInt(0);
        /** @var Amount $limit */
        $limit = $this->credit_limit ?? Amount::fromInt(0);

        return $balance->toInt() > $limit->toInt();
    }
}
