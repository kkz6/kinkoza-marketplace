<?php

use Illuminate\Support\Facades\Route;
use Kinkoza\Storefront\Http\Livewire\AccountDashboard;

Route::middleware(['web', 'auth', 'verified'])
    ->group(function (): void {
        Route::get('/dashboard', AccountDashboard::class)
            ->name('dashboard');
    });
