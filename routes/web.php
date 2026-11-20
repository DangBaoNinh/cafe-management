<?php

use App\Http\Controllers\Admin\AccountController;
use App\Http\Controllers\Admin\CategoryController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'customer.home')->name('home');

Route::view('dashboard', 'customer.account')->middleware('auth')->name('dashboard');

Route::prefix('admin')->name('admin.')->middleware(['auth', 'can:access-admin'])->group(function () {
    Route::view('/', 'admin.dashboard')->name('dashboard');
    Route::resource('categories', CategoryController::class)->only(['index', 'create', 'store', 'edit', 'update']);
    Route::get('accounts', [AccountController::class, 'index'])->name('accounts.index');

    Route::middleware('password.confirm')->group(function () {
        Route::get('accounts/{account}/edit', [AccountController::class, 'edit'])->name('accounts.edit');
        Route::patch('accounts/{account}', [AccountController::class, 'update'])->name('accounts.update');
    });
});

require __DIR__.'/settings.php';
