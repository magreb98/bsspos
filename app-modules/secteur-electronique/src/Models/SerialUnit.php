<?php

declare(strict_types=1);

namespace Modules\SecteurElectronique\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Commerce\Internal\Models\Product;
use Modules\Commerce\Internal\Models\SaleLine;
use Modules\SecteurElectronique\Enums\SerialStatus;

final class SerialUnit extends Model
{
    use HasUuids;

    protected $fillable = [
        'product_id',
        'serial_number',
        'status',
        'sale_line_id',
    ];

    protected $casts = [
        'status' => SerialStatus::class,
    ];

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    /** @return BelongsTo<SaleLine, $this> */
    public function saleLine(): BelongsTo
    {
        return $this->belongsTo(SaleLine::class, 'sale_line_id');
    }
}
