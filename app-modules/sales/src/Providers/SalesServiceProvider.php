<?php

declare(strict_types=1);

namespace Kinkoza\Sales\Providers;

use Illuminate\Support\ServiceProvider;
use Kinkoza\Sales\Contracts\Services\CheckoutServiceInterface;
use Kinkoza\Sales\Services\CheckoutService;

class SalesServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(CheckoutServiceInterface::class, CheckoutService::class);
    }

    public function boot(): void {}
}
