<?php

declare(strict_types=1);

namespace Modules\SecteurElectronique\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Commerce\Internal\Models\Product;
use Modules\SecteurElectronique\Enums\DeviceCategory;

final class DeviceSpec extends Model
{
    use HasUuids;

    protected $fillable = [
        'product_id',
        'category',
        'brand',
        'model',
        'color',
        'model_year',
        'imei_required',
        'warranty_months',
        // Screen
        'screen_size_inches',
        'screen_resolution',
        'panel_type',
        // Processor / Memory
        'processor',
        'ram_gb',
        'storage_gb',
        // Connectivity
        'bluetooth',
        'wifi',
        'nfc',
        'cellular_network',
        // Battery
        'battery_mah',
        // Camera
        'main_camera_mp',
        // Audio
        'power_watts',
        // OS
        'operating_system',
        // Physical
        'weight_grams',
        // Catch-all
        'additional_specs',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'category'      => DeviceCategory::class,
        'imei_required' => 'boolean',
        'bluetooth'     => 'boolean',
        'wifi'          => 'boolean',
        'nfc'           => 'boolean',
        'additional_specs' => 'array',
    ];

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }
}
