<?php

use Illuminate\Support\Facades\Route;
use Kinkoza\Storefront\Actions\UpdateLocale;
use Kinkoza\Storefront\Http\Livewire\CartShow;
use Kinkoza\Storefront\Http\Livewire\CheckoutShow;
use Kinkoza\Storefront\Http\Livewire\CreateListing;
use Kinkoza\Storefront\Http\Livewire\ListingShow;
use Kinkoza\Storefront\Http\Livewire\ListingsIndex;
use Kinkoza\Storefront\Http\Livewire\OrderConfirmation;

Route::middleware('web')->group(function (): void {
    Route::get('/', ListingsIndex::class)
        ->middleware('throttle:storefront-search')
        ->name('home');

    Route::post('/locale/{locale}', UpdateLocale::class)
        ->whereIn('locale', array_keys(config('locales.supported', [])))
        ->middleware('throttle:storefront-action')
        ->name('locale.update');

    Route::get('/listings/{slug}', ListingShow::class)
        ->middleware('throttle:storefront-action')
        ->name('storefront.listings.show');

    Route::get('/cart', CartShow::class)
        ->middleware('throttle:storefront-action')
        ->name('storefront.cart.show');

    Route::middleware(['auth', 'verified'])->group(function (): void {
        Route::get('/sell', CreateListing::class)
            ->middleware('throttle:storefront-action')
            ->name('storefront.listings.create');

        Route::get('/checkout', CheckoutShow::class)
            ->middleware('throttle:storefront-checkout')
            ->name('storefront.checkout.show');

        Route::get('/orders/{order}', OrderConfirmation::class)
            ->name('storefront.orders.show');
    });
});
