<?php

declare(strict_types=1);

namespace Modules\Commerce\Public;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Modules\Commerce\Public\Enums\Availability;

final class CatalogProjection extends Model
{
    use HasUuids;

    protected $table = 'catalog_projections';

    public $timestamps = false;

    /** @var list<string> */
    protected $fillable = [
        'product_id',
        'reference',
        'label',
        'selling_price',
        'availability',
        'updated_at',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'selling_price' => 'integer',
        'availability' => Availability::class,
        'updated_at' => 'datetime',
    ];
}
