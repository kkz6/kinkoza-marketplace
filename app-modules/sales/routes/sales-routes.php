<?php

use Illuminate\Support\Facades\Route;
use Kinkoza\Storefront\Http\Livewire\CheckoutShow;
use Kinkoza\Storefront\Http\Livewire\OrderConfirmation;

Route::middleware(['web', 'auth', 'verified'])
    ->group(function (): void {
        Route::get('/checkout', CheckoutShow::class)
            ->middleware('throttle:storefront-checkout')
            ->name('storefront.checkout.show');

        Route::get('/orders/{order}', OrderConfirmation::class)
            ->name('storefront.orders.show');
    });
