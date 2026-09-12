<?php

declare(strict_types=1);

namespace Modules\Commerce\Internal\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Modules\Commerce\Internal\Models\Product;
use Modules\Commerce\Public\CatalogProjection;
use Modules\Commerce\Public\Enums\Availability;

final class CatalogProjectionService
{
    public function rebuild(Product $product): CatalogProjection
    {
        $totalQuantity = (int) DB::table('stock_levels')
            ->where('product_id', $product->id)
            ->sum('quantity');

        $availability = match (true) {
            $totalQuantity > 10 => Availability::Available,
            $totalQuantity >= 1 => Availability::LowStock,
            default => Availability::OutOfStock,
        };

        /** @var CatalogProjection */
        $projection = CatalogProjection::updateOrCreate(
            ['product_id' => $product->id],
            [
                'reference' => $product->reference,
                'label' => $product->label,
                'selling_price' => $product->selling_price?->toInt() ?? 0,
                'availability' => $availability,
                'updated_at' => now(),
            ]
        );

        // Invalidate the public catalog cache (PublicCatalogReader) so this
        // write is visible immediately instead of waiting out the TTL.
        Cache::forget('catalog.list');
        Cache::forget("catalog.ref.{$product->reference}");

        return $projection;
    }
}
