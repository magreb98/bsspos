<?php

declare(strict_types=1);

namespace Modules\Commerce\Internal\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class DailyAggregate extends Model
{
    use HasUuids;

    protected $fillable = [
        'point_of_sale_id',
        'date',
        'sale_count',
        'total_excluding_tax',
        'total_tax',
        'total_including_tax',
        'is_provisional',
        'computed_at',
    ];

    protected $casts = [
        'date'                  => 'string',
        'sale_count'            => 'integer',
        'total_excluding_tax'   => 'integer',
        'total_tax'             => 'integer',
        'total_including_tax'   => 'integer',
        'is_provisional'        => 'boolean',
        'computed_at'           => 'datetime',
    ];

    /** @return BelongsTo<PointOfSale, $this> */
    public function pointOfSale(): BelongsTo
    {
        return $this->belongsTo(PointOfSale::class, 'point_of_sale_id');
    }
}
