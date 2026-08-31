<?php

declare(strict_types=1);

namespace Kinkoza\Sales\Providers;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Kinkoza\Sales\Contracts\Services\CheckoutServiceInterface;
use Kinkoza\Sales\Events\OrderPlaced;
use Kinkoza\Sales\Listeners\SendOrderConfirmation;
use Kinkoza\Sales\Services\CheckoutService;

class SalesServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(CheckoutServiceInterface::class, CheckoutService::class);
    }

    public function boot(): void
    {
        Event::listen(OrderPlaced::class, SendOrderConfirmation::class);
    }
}
