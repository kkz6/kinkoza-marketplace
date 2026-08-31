<?php

declare(strict_types=1);

namespace Kinkoza\Cart\Providers;

use Illuminate\Support\ServiceProvider;
use Kinkoza\Cart\Contracts\Services\CartServiceInterface;
use Kinkoza\Cart\Services\CartService;

class CartServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(CartServiceInterface::class, CartService::class);
    }

    public function boot(): void {}
}
