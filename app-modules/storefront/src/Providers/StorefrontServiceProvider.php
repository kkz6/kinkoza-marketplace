<?php

namespace Kinkoza\Storefront\Providers;

use Illuminate\Auth\Middleware\EnsureEmailIsVerified;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

class StorefrontServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->loadTranslationsFrom(__DIR__.'/../../resources/lang', 'storefront');

        Livewire::addNamespace(
            'storefront',
            classNamespace: 'Kinkoza\\Storefront\\Http\\Livewire',
        );

        Livewire::addPersistentMiddleware([
            EnsureEmailIsVerified::class,
            ThrottleRequests::class,
        ]);

        RateLimiter::for('storefront-search', fn (Request $request): Limit => Limit::perMinute(120)
            ->by($request->user()?->getAuthIdentifier() ?? $request->ip()));

        RateLimiter::for('storefront-action', fn (Request $request): Limit => Limit::perMinute(30)
            ->by($request->user()?->getAuthIdentifier() ?? $request->ip()));

        RateLimiter::for('storefront-checkout', fn (Request $request): Limit => Limit::perMinute(10)
            ->by($request->user()?->getAuthIdentifier() ?? $request->ip()));
    }
}
