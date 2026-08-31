<?php

namespace Kinkoza\Catalog\Support;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Kinkoza\Catalog\Models\Listing;

class CatalogCache
{
    private const FRESH_SECONDS = 60;

    private const STALE_SECONDS = 300;

    private const VERSION_KEY = 'catalog:listings:version';

    /** @return Collection<int, Listing> */
    public function featured(int $limit = 12): Collection
    {
        return $this->featuredPublished($limit);
    }

    /** @return Collection<int, Listing> */
    public function featuredPublished(int $limit = 12): Collection
    {
        $limit = max(1, min($limit, 50));
        $key = "catalog:listings:featured:v{$this->version()}:limit:{$limit}";

        /** @var Collection<int, Listing> $listings */
        $listings = Cache::flexible(
            $key,
            [self::FRESH_SECONDS, self::STALE_SECONDS],
            fn (): Collection => Listing::query()
                ->published()
                ->where('inventory_quantity', '>', 0)
                ->latest('online_at')
                ->latest('sequence')
                ->limit($limit)
                ->get(),
        );

        return $listings;
    }

    public function invalidate(): void
    {
        Cache::add(self::VERSION_KEY, 1);

        $version = Cache::increment(self::VERSION_KEY);

        if ($version !== false) {
            return;
        }

        Cache::forever(self::VERSION_KEY, $this->version() + 1);
    }

    public function version(): int
    {
        return (int) Cache::rememberForever(
            self::VERSION_KEY,
            static fn (): int => 1,
        );
    }
}
