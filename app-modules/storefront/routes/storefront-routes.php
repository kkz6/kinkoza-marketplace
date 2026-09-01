<?php

use Illuminate\Support\Facades\Route;
use Kinkoza\Storefront\Actions\UpdateLocale;
use Kinkoza\Storefront\Http\Livewire\CartShow;
use Kinkoza\Storefront\Http\Livewire\CheckoutShow;
use Kinkoza\Storefront\Http\Livewire\CreateListing;
use Kinkoza\Storefront\Http\Livewire\ListingShow;
use Kinkoza\Storefront\Http\Livewire\ListingsIndex;
use Kinkoza\Storefront\Http\Livewire\OrderConfirmation;

$supportedLocales = config('locales.supported', []);

if (! is_array($supportedLocales)) {
    throw new UnexpectedValueException('Supported locales configuration must be an array.');
}

$supportedLocaleNames = array_values(array_filter(array_keys($supportedLocales), is_string(...)));

Route::middleware('web')->group(function () use ($supportedLocaleNames): void {
    Route::get('/', ListingsIndex::class)
        ->middleware('throttle:storefront-search')
        ->name('home');

    Route::post('/locale/{locale}', UpdateLocale::class)
        ->whereIn('locale', $supportedLocaleNames)
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
