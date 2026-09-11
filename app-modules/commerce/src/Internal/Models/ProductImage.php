<?php

declare(strict_types=1);

namespace Modules\Commerce\Internal\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class ProductImage extends Model
{
    use HasUuids;

    protected $fillable = ['product_id', 'url', 'position'];

    protected $casts = ['position' => 'integer'];

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }
}
