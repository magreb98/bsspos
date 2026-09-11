<?php

declare(strict_types=1);

namespace Modules\Commerce\Internal\Models;

use App\Platform\Money\Casts\AmountCast;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Commerce\Internal\Enums\Granularity;

final class Product extends Model
{
    use HasUuids;

    protected $fillable = [
        'reference', 'label', 'family_id',
        'selling_price', 'vat_rate', 'granularity',
        'attributes', 'active',
    ];

    protected $casts = [
        'selling_price' => AmountCast::class,
        'vat_rate'      => 'decimal:2',
        'granularity'   => Granularity::class,
        'attributes'    => 'array',
        'active'        => 'boolean',
    ];

    /** @return BelongsTo<Family, $this> */
    public function family(): BelongsTo
    {
        return $this->belongsTo(Family::class, 'family_id');
    }

    /** @return HasMany<ProductImage, $this> */
    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class, 'product_id')->orderBy('position');
    }

    /** @return HasMany<ProductVariant, $this> */
    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class, 'product_id');
    }

    /** @return HasMany<ProductBatch, $this> */
    public function batches(): HasMany
    {
        return $this->hasMany(ProductBatch::class, 'product_id');
    }

    public function isService(): bool
    {
        return $this->granularity === Granularity::Service;
    }
}
