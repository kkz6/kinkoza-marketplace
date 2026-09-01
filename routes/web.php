<?php

use Illuminate\Support\Facades\Route;
use Kinkoza\Storefront\Http\Livewire\AccountDashboard;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', AccountDashboard::class)->name('dashboard');
});

require __DIR__.'/settings.php';
