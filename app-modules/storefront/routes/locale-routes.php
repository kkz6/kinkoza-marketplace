<?php

use Illuminate\Support\Facades\Route;
use Kinkoza\Storefront\Actions\UpdateLocale;

$supportedLocales = config('locales.supported', []);

if (! is_array($supportedLocales)) {
    throw new UnexpectedValueException('Supported locales configuration must be an array.');
}

$supportedLocaleNames = array_values(array_filter(array_keys($supportedLocales), is_string(...)));

Route::middleware('web')
    ->group(function () use ($supportedLocaleNames): void {
        Route::post('/locale/{locale}', UpdateLocale::class)
            ->whereIn('locale', $supportedLocaleNames)
            ->middleware('throttle:storefront-action')
            ->name('locale.update');
    });
