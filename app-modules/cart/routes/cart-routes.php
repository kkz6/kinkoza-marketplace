<?php

use Illuminate\Support\Facades\Route;
use Kinkoza\Storefront\Http\Livewire\CartShow;

Route::middleware('web')
    ->group(function (): void {
        Route::get('/cart', CartShow::class)
            ->middleware('throttle:storefront-action')
            ->name('storefront.cart.show');
    });
