<?php

use Illuminate\Support\Facades\Route;

Route::view('/', 'customer.home')->name('home');

Route::view('dashboard', 'customer.account')->middleware('auth')->name('dashboard');

Route::prefix('admin')->name('admin.')->middleware(['auth', 'can:access-admin'])->group(function () {
    Route::view('/', 'admin.dashboard')->name('dashboard');
});

require __DIR__.'/settings.php';
