<?php

declare(strict_types=1);

namespace Modules\Commerce\Internal\Models;

use App\Platform\Money\Casts\AmountCast;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class ReceptionLine extends Model
{
    use HasUuids;

    protected $fillable = [
        'reception_id',
        'product_id',
        'quantity_expected',
        'quantity_received',
        'unit_cost',
    ];

    protected $casts = [
        'quantity_expected' => 'integer',
        'quantity_received' => 'integer',
        'unit_cost'         => AmountCast::class,
    ];

    /** @return BelongsTo<Reception, $this> */
    public function reception(): BelongsTo
    {
        return $this->belongsTo(Reception::class, 'reception_id');
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }
}
