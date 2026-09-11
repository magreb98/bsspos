<?php

declare(strict_types=1);

namespace Modules\SecteurElectronique\Http\Data;

use Spatie\LaravelData\Data;
use Spatie\LaravelData\Optional;

final class StoreDeviceSpecData extends Data
{
    /**
     * @param array<string, mixed>|Optional $additional_specs
     */
    public function __construct(
        public readonly string $product_id,
        public readonly string $category,
        public readonly string $brand,
        public readonly string $model,
        public readonly int $warranty_months,
        public readonly bool|Optional $imei_required,
        public readonly string|Optional $color,
        public readonly int|Optional $model_year,
        public readonly float|Optional $screen_size_inches,
        public readonly string|Optional $screen_resolution,
        public readonly string|Optional $panel_type,
        public readonly string|Optional $processor,
        public readonly int|Optional $ram_gb,
        public readonly int|Optional $storage_gb,
        public readonly bool|Optional $bluetooth,
        public readonly bool|Optional $wifi,
        public readonly bool|Optional $nfc,
        public readonly string|Optional $cellular_network,
        public readonly int|Optional $battery_mah,
        public readonly int|Optional $main_camera_mp,
        public readonly int|Optional $power_watts,
        public readonly string|Optional $operating_system,
        public readonly int|Optional $weight_grams,
        public readonly array|Optional $additional_specs,
    ) {
    }
}
