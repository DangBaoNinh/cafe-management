<?php

use App\Http\Controllers\Admin\AccountController;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\Admin\ProductOptionController;
use App\Http\Controllers\Admin\ProductSizeController;
use App\Http\Controllers\Admin\ToppingController;
use App\Http\Controllers\CatalogController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'customer.home')->name('home');
Route::get('menu', [CatalogController::class, 'index'])->name('menu.index');
Route::get('menu/{slug}', [CatalogController::class, 'show'])->name('menu.show');

Route::view('dashboard', 'customer.account')->middleware('auth')->name('dashboard');

Route::prefix('admin')->name('admin.')->middleware(['auth', 'can:access-admin'])->group(function () {
    Route::view('/', 'admin.dashboard')->name('dashboard');
    Route::resource('categories', CategoryController::class)->only(['index', 'create', 'store', 'edit', 'update']);
    Route::resource('products', ProductController::class)->only(['index', 'create', 'store', 'edit', 'update']);
    Route::resource('toppings', ToppingController::class)->only(['index', 'create', 'store', 'edit', 'update']);
    Route::resource('products.sizes', ProductSizeController::class)->only(['create', 'store', 'edit', 'update'])->scoped();
    Route::get('products/{product}/options', [ProductOptionController::class, 'show'])->name('products.options');
    Route::post('products/{product}/toppings', [ProductOptionController::class, 'attach'])->name('products.toppings.store');
    Route::delete('products/{product}/toppings/{topping}', [ProductOptionController::class, 'detach'])->name('products.toppings.destroy');
    Route::get('accounts', [AccountController::class, 'index'])->name('accounts.index');

    Route::middleware('password.confirm')->group(function () {
        Route::get('accounts/{account}/edit', [AccountController::class, 'edit'])->name('accounts.edit');
        Route::patch('accounts/{account}', [AccountController::class, 'update'])->name('accounts.update');
    });
});

require __DIR__.'/settings.php';
