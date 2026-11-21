<?php

use App\Http\Controllers\Admin\AccountController;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\CouponController;
use App\Http\Controllers\Admin\CustomerController;
use App\Http\Controllers\Admin\DeliverySettingsController;
use App\Http\Controllers\Admin\OrderController;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\Admin\ProductOptionController;
use App\Http\Controllers\Admin\ProductSizeController;
use App\Http\Controllers\Admin\ToppingController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\CatalogController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\CustomerOrderController;
use App\Http\Controllers\DeliveryFeeController;
use App\Http\Controllers\ManualPaymentController;
use App\Http\Controllers\OrderHandoffController;
use App\Http\Controllers\OrderIntakeController;
use App\Http\Controllers\OrderPreparationController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'customer.home')->name('home');
Route::get('menu', [CatalogController::class, 'index'])->name('menu.index');
Route::get('menu/{slug}', [CatalogController::class, 'show'])->name('menu.show');

Route::view('dashboard', 'customer.account')->middleware('auth')->name('dashboard');

Route::prefix('cart')->name('cart.')->middleware(['auth', 'can:manage-cart'])->group(function () {
    Route::get('/', [CartController::class, 'index'])->name('index');
    Route::get('delivery', DeliveryFeeController::class)->name('delivery');
    Route::patch('{item}', [CartController::class, 'update'])->whereNumber('item')->name('update');
    Route::delete('{item}', [CartController::class, 'destroy'])->whereNumber('item')->name('destroy');
});

Route::prefix('checkout')->name('checkout.')->middleware(['auth', 'can:manage-cart'])->group(function () {
    Route::get('/', [CheckoutController::class, 'create'])->name('create');
    Route::post('review', [CheckoutController::class, 'review'])->name('review');
    Route::post('/', [CheckoutController::class, 'store'])->name('store');
    Route::get('orders/{number}', [CheckoutController::class, 'confirmation'])->name('confirmation');
});

Route::prefix('orders')->name('orders.')->middleware(['auth', 'can:access-customer-orders'])->group(function () {
    Route::get('/', [CustomerOrderController::class, 'index'])->name('index');
    Route::get('{number}', [CustomerOrderController::class, 'show'])->name('show');
    Route::post('{number}/cancel', [CustomerOrderController::class, 'cancel'])->name('cancel');
});

Route::prefix('operations/orders')->name('operations.orders.')->middleware(['auth', 'can:operate-orders'])->group(function () {
    Route::get('/', [OrderIntakeController::class, 'index'])->name('index');
    Route::get('{number}', [OrderIntakeController::class, 'show'])->name('show');
    Route::post('{number}/decision', [OrderIntakeController::class, 'decide'])->name('decide');
    Route::post('{number}/preparation', [OrderPreparationController::class, 'update'])->name('prepare');
    Route::post('{number}/handoff', [OrderHandoffController::class, 'update'])->name('handoff');
});

Route::get('operations/preparation', [OrderPreparationController::class, 'index'])
    ->middleware(['auth', 'can:operate-orders'])->name('operations.preparation.index');

Route::get('operations/handoff', [OrderHandoffController::class, 'index'])
    ->middleware(['auth', 'can:operate-orders'])->name('operations.handoff.index');

Route::prefix('operations/payments')->name('operations.payments.')->middleware(['auth', 'can:operate-orders'])->group(function () {
    Route::get('/', [ManualPaymentController::class, 'index'])->name('index');
    Route::get('{number}', [ManualPaymentController::class, 'show'])->name('show');
    Route::post('{number}/reconcile', [ManualPaymentController::class, 'reconcile'])->name('reconcile');
    Route::post('{number}/refund', [ManualPaymentController::class, 'refund'])->middleware('can:access-admin')->name('refund');
});

Route::prefix('admin')->name('admin.')->middleware(['auth', 'can:access-admin'])->group(function () {
    Route::view('/', 'admin.dashboard')->name('dashboard');
    Route::get('orders', [OrderController::class, 'index'])->name('orders.index');
    Route::get('orders/{number}', [OrderController::class, 'show'])->name('orders.show');
    Route::post('orders/{number}/cancel', [OrderController::class, 'cancel'])->name('orders.cancel');
    Route::get('customers', [CustomerController::class, 'index'])->name('customers.index');
    Route::get('customers/{customer}', [CustomerController::class, 'show'])->whereNumber('customer')->name('customers.show');
    Route::get('delivery-settings', [DeliverySettingsController::class, 'edit'])->name('delivery-settings.edit');
    Route::patch('delivery-settings', [DeliverySettingsController::class, 'update'])->name('delivery-settings.update');
    Route::resource('categories', CategoryController::class)->only(['index', 'create', 'store', 'edit', 'update']);
    Route::resource('coupons', CouponController::class)->only(['index', 'create', 'store', 'edit', 'update']);
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
