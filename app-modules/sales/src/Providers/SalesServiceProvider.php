<?php

declare(strict_types=1);

namespace Kinkoza\Sales\Providers;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Kinkoza\Sales\Actions\SendOrderConfirmation;
use Kinkoza\Sales\Events\OrderPlaced;

class SalesServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Event::listen(OrderPlaced::class, SendOrderConfirmation::class);
    }
}
