<?php

namespace Kinkoza\Catalog\Providers;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Kinkoza\Catalog\Models\Listing;
use Kinkoza\Catalog\Policies\ListingPolicy;
use Kinkoza\Catalog\Support\CatalogCache;

class CatalogServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(CatalogCache::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');

        Gate::policy(Listing::class, ListingPolicy::class);
    }
}
