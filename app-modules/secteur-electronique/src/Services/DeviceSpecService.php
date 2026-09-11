<?php

declare(strict_types=1);

namespace Modules\SecteurElectronique\Services;

use Modules\Commerce\Internal\Models\Product;
use Modules\SecteurElectronique\Models\DeviceSpec;

final class DeviceSpecService
{
    /**
     * Creates and attaches a device specification to a product.
     *
     * @param array<string, mixed> $data
     */
    public function attach(Product $product, array $data): DeviceSpec
    {
        if ($this->forProduct($product) !== null) {
            throw new \DomainException("Product [{$product->id}] already has a device specification.");
        }

        return DeviceSpec::create([...$data, 'product_id' => $product->id]);
    }

    /**
     * Updates an existing device specification.
     *
     * @param array<string, mixed> $data
     */
    public function update(DeviceSpec $spec, array $data): DeviceSpec
    {
        $spec->update($data);

        return $spec->refresh();
    }

    /**
     * Returns the device specification for a product, or null if none exists.
     */
    public function forProduct(Product $product): ?DeviceSpec
    {
        return DeviceSpec::where('product_id', $product->id)->first();
    }
}
