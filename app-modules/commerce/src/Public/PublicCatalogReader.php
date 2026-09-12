<?php

declare(strict_types=1);

namespace Modules\Commerce\Public;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

final class PublicCatalogReader
{
    /** @return Collection<int, CatalogProjection> */
    public function list(): Collection
    {
        /** @var Collection<int, CatalogProjection> */
        return Cache::remember('catalog.list', now()->addMinutes(30), fn () => CatalogProjection::all());
    }

    public function find(string $reference): ?CatalogProjection
    {
        /** @var CatalogProjection|null */
        return Cache::remember("catalog.ref.{$reference}", now()->addMinutes(30), fn () => CatalogProjection::where('reference', $reference)->first());
    }
}
