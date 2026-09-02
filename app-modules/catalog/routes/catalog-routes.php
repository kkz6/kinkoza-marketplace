<?php

use Illuminate\Support\Facades\Route;
use Kinkoza\Storefront\Http\Livewire\CreateListing;
use Kinkoza\Storefront\Http\Livewire\ListingShow;
use Kinkoza\Storefront\Http\Livewire\ListingsIndex;

Route::middleware('web')
    ->group(function (): void {
        Route::get('/', ListingsIndex::class)
            ->middleware('throttle:storefront-search')
            ->name('home');

        Route::get('/listings/{slug}', ListingShow::class)
            ->middleware('throttle:storefront-action')
            ->name('storefront.listings.show');

        Route::get('/sell', CreateListing::class)
            ->middleware(['auth', 'verified', 'throttle:storefront-action'])
            ->name('storefront.listings.create');
    });
