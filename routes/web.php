<?php

use App\Http\Controllers\AccountDashboardController;
use App\Http\Controllers\Admin\AccountController;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\CounterOrderController;
use App\Http\Controllers\Admin\CouponController;
use App\Http\Controllers\Admin\CustomerController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\DeliverySettingsController;
use App\Http\Controllers\Admin\InventoryController;
use App\Http\Controllers\Admin\InventoryRecipeController;
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
use App\Http\Controllers\DemoDeliveryController;
use App\Http\Controllers\DemoPaymentController;
use App\Http\Controllers\ManualPaymentController;
use App\Http\Controllers\OperationsInvoiceController;
use App\Http\Controllers\OrderHandoffController;
use App\Http\Controllers\OrderIntakeController;
use App\Http\Controllers\OrderPreparationController;
use App\Http\Controllers\OfferController;
use App\Http\Controllers\PublicSeatingController;
use App\Http\Controllers\ReservationController;
use App\Http\Controllers\ReservationOperationsController;
use App\Http\Controllers\ScheduledPickupController;
use App\Http\Controllers\StorefrontChatController;
use App\Http\Controllers\TableInvoiceController;
use App\Http\Controllers\TableManagementController;
use App\Http\Controllers\TableOrderController;
use App\Http\Controllers\TablePosController;
use App\Http\Controllers\TableSessionOperationsController;
use App\Http\Middleware\EnsureDemoDeliveryEnabled;
use App\Http\Middleware\EnsureDemoPaymentEnabled;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Facades\Route;

Route::get('/', [CatalogController::class, 'home'])->name('home');
Route::view('cau-chuyen', 'customer.story')->name('story');
Route::view('khong-gian', 'customer.spaces')->name('spaces');
Route::get('khong-gian/snapshot', PublicSeatingController::class)->middleware('throttle:60,1')->name('spaces.snapshot');
Route::get('menu', [CatalogController::class, 'index'])->name('menu.index');
Route::get('menu/{slug}', [CatalogController::class, 'show'])->name('menu.show');
Route::get('uu-dai', OfferController::class)->name('offers.index');
Route::post('tro-chuyen', StorefrontChatController::class)
    ->middleware('throttle:storefront-chat')->name('storefront-chat.reply');

Route::prefix('goi-mon/{reservationSeat:code}')->name('table-orders.')->group(function () {
    Route::get('access/{tableSession}', [TableOrderController::class, 'access'])->whereUuid('tableSession')->middleware('signed')->name('access');
    Route::get('order', [TableOrderController::class, 'pos'])->name('pos');
    Route::post('invoices', [TableOrderController::class, 'invoiceStore'])->name('invoice.store');
    Route::get('invoices/{tableInvoice}', [TableOrderController::class, 'invoiceShow'])->whereUuid('tableInvoice')->name('invoice.show');
    Route::post('invoices/{tableInvoice}/report', [TableOrderController::class, 'reportPayment'])->whereUuid('tableInvoice')->middleware('throttle:30,1')->name('invoice.report');
    Route::get('/', [TableOrderController::class, 'show'])->name('show');
    Route::post('join', [TableOrderController::class, 'join'])->middleware('throttle:table-order-access')->name('join');
    Route::post('rounds', [TableOrderController::class, 'store'])->name('store');
    Route::post('checkout', [TableOrderController::class, 'checkout'])->name('checkout');
});

Route::get('dashboard', AccountDashboardController::class)->middleware('auth')->name('dashboard');

Route::prefix('cart')->name('cart.')->middleware(['auth', 'can:manage-cart'])->group(function () {
    Route::get('/', [CartController::class, 'index'])->name('index');
    Route::post('from-chat', [StorefrontChatController::class, 'addToCart'])->name('add-from-chat');
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
    Route::post('{number}/reschedule-pickup', [ScheduledPickupController::class, 'reschedule'])->name('reschedule-pickup');
    Route::prefix('{number}/demo-payment')->name('demo-payment.')->middleware(EnsureDemoPaymentEnabled::class)->group(function () {
        Route::post('/', [DemoPaymentController::class, 'start'])->name('start');
        Route::get('{reference}', [DemoPaymentController::class, 'show'])->whereUuid('reference')->name('show');
        Route::post('{reference}', [DemoPaymentController::class, 'simulate'])->whereUuid('reference')->name('simulate');
    });
});

Route::post('payments/demo/callback', [DemoPaymentController::class, 'callback'])
    ->middleware(EnsureDemoPaymentEnabled::class)->withoutMiddleware(PreventRequestForgery::class)->name('payments.demo.callback');

Route::prefix('reservations')->name('reservations.')->middleware(['auth', 'can:access-customer-orders'])->group(function () {
    Route::get('/', [ReservationController::class, 'index'])->name('index');
    Route::get('create', [ReservationController::class, 'create'])->name('create');
    Route::post('/', [ReservationController::class, 'store'])->name('store');
    Route::get('{code}', [ReservationController::class, 'show'])->name('show');
    Route::post('{code}/reschedule', [ReservationController::class, 'reschedule'])->name('reschedule');
    Route::post('{code}/cancel', [ReservationController::class, 'cancel'])->name('cancel');
});

Route::post('deliveries/demo/callback', [DemoDeliveryController::class, 'callback'])
    ->middleware(EnsureDemoDeliveryEnabled::class)->withoutMiddleware(PreventRequestForgery::class)->name('deliveries.demo.callback');

Route::prefix('operations/orders')->name('operations.orders.')->middleware(['auth', 'can:operate-orders'])->group(function () {
    Route::get('/', [OrderIntakeController::class, 'index'])->name('index');
    Route::get('{number}', [OrderIntakeController::class, 'show'])->name('show');
    Route::post('{number}/decision', [OrderIntakeController::class, 'decide'])->name('decide');
    Route::post('{number}/preparation', [OrderPreparationController::class, 'update'])->name('prepare');
    Route::post('{number}/handoff', [OrderHandoffController::class, 'update'])->name('handoff');
    Route::prefix('{number}/demo-shipment')->name('demo-shipment.')->middleware(EnsureDemoDeliveryEnabled::class)->group(function () {
        Route::post('/', [DemoDeliveryController::class, 'create'])->name('create');
        Route::post('events', [DemoDeliveryController::class, 'simulate'])->name('simulate');
    });
});

Route::get('operations/preparation', [OrderPreparationController::class, 'index'])
    ->middleware(['auth', 'can:operate-orders'])->name('operations.preparation.index');

Route::get('operations/handoff', [OrderHandoffController::class, 'index'])
    ->middleware(['auth', 'can:operate-orders'])->name('operations.handoff.index');

Route::prefix('operations/pickups')->name('operations.pickups.')->middleware(['auth', 'can:operate-orders'])->group(function () {
    Route::get('/', [ScheduledPickupController::class, 'index'])->name('index');
    Route::post('lookup', [ScheduledPickupController::class, 'lookup'])->name('lookup');
    Route::get('{code}', [ScheduledPickupController::class, 'show'])->name('show');
    Route::post('{code}/arrival', [ScheduledPickupController::class, 'arrive'])->name('arrive');
    Route::post('{code}/handoff', [ScheduledPickupController::class, 'handoff'])->name('handoff');
    Route::post('{code}/fees/{fee}/resolve', [ScheduledPickupController::class, 'resolve'])->whereNumber('fee')->name('resolve');
    Route::post('{code}/fees/{fee}/counter', [ScheduledPickupController::class, 'moveToCounter'])->whereNumber('fee')->name('counter');
    Route::post('{code}/fees/{fee}/refund', [ScheduledPickupController::class, 'refund'])->whereNumber('fee')->middleware('can:access-admin')->name('refund');
});

Route::prefix('operations/payments')->name('operations.payments.')->middleware(['auth', 'can:operate-orders'])->group(function () {
    Route::get('/', [ManualPaymentController::class, 'index'])->name('index');
    Route::get('{number}', [ManualPaymentController::class, 'show'])->name('show');
    Route::post('{number}/reconcile', [ManualPaymentController::class, 'reconcile'])->name('reconcile');
    Route::post('{number}/refund', [ManualPaymentController::class, 'refund'])->middleware('can:access-admin')->name('refund');
    Route::post('{number}/demo-reconcile', [DemoPaymentController::class, 'reconcile'])
        ->middleware([EnsureDemoPaymentEnabled::class, 'can:access-admin'])->name('demo-reconcile');
});

Route::prefix('operations/invoices')->name('operations.invoices.')->middleware(['auth', 'can:operate-orders'])->group(function () {
    Route::get('prepaid/{tableInvoice}', [TableInvoiceController::class, 'show'])->whereUuid('tableInvoice')->name('prepaid.show');
    Route::post('prepaid/{tableInvoice}/pay', [TableInvoiceController::class, 'pay'])->whereUuid('tableInvoice')->name('prepaid.pay');
    Route::get('/', [OperationsInvoiceController::class, 'index'])->name('index');
    Route::get('history', [OperationsInvoiceController::class, 'index'])->name('history');
    Route::get('orders/{number}', [OperationsInvoiceController::class, 'order'])->name('orders.show');
    Route::get('tables/{tableSession}', [OperationsInvoiceController::class, 'table'])->whereUuid('tableSession')->name('tables.show');
});

Route::prefix('operations/table-sessions')->name('operations.table-sessions.')->middleware(['auth', 'can:operate-orders'])->group(function () {
    Route::post('tables/{reservationSeat:code}/order', [TableManagementController::class, 'order'])->name('table.order');
    Route::post('tables/{reservationSeat:code}/status', [TableManagementController::class, 'status'])->name('table.status');
    Route::get('{tableSession}/order', [TablePosController::class, 'show'])->whereUuid('tableSession')->name('pos');
    Route::post('{tableSession}/order', [TablePosController::class, 'store'])->whereUuid('tableSession')->name('pos.store');
    Route::get('/', [TableSessionOperationsController::class, 'index'])->name('index');
    Route::get('seats/{reservationSeat:code}/qr', [TableSessionOperationsController::class, 'qr'])->name('qr');
    Route::post('seats/{reservationSeat:code}', [TableSessionOperationsController::class, 'open'])->name('open');
    Route::get('{tableSession}', [TableSessionOperationsController::class, 'show'])->whereUuid('tableSession')->name('show');
    Route::post('{tableSession}/rounds', [TableSessionOperationsController::class, 'store'])->whereUuid('tableSession')->name('store');
    Route::post('{tableSession}/rounds/{round}', [TableSessionOperationsController::class, 'advance'])->whereUuid('tableSession')->whereNumber('round')->name('advance');
    Route::post('{tableSession}/actions/{action}', [TableSessionOperationsController::class, 'update'])
        ->whereUuid('tableSession')->whereIn('action', ['lock', 'bill', 'pay', 'close'])->name('update');
});

Route::prefix('operations/reservations')->name('operations.reservations.')->middleware(['auth', 'can:operate-orders'])->group(function () {
    Route::get('/', [ReservationOperationsController::class, 'index'])->name('index');
    Route::get('seats', [ReservationOperationsController::class, 'seats'])->name('seats');
    Route::post('hours', [ReservationOperationsController::class, 'hours'])->middleware('can:access-admin')->name('hours');
    Route::post('seats/{seat}/walk-in', [ReservationOperationsController::class, 'walkIn'])->whereNumber('seat')->name('walk-in');
    Route::post('seats/{seat}/active', [ReservationOperationsController::class, 'seatActive'])->whereNumber('seat')->middleware('can:access-admin')->name('seat-active');
    Route::post('lookup', [ReservationOperationsController::class, 'lookup'])->name('lookup');
    Route::get('{code}', [ReservationOperationsController::class, 'show'])->name('show');
    Route::post('{code}/check-in', [ReservationOperationsController::class, 'checkIn'])->name('check-in');
    Route::post('{code}/release', [ReservationOperationsController::class, 'release'])->name('release');
    Route::post('{code}/cancel', [ReservationOperationsController::class, 'cancel'])->name('cancel');
    Route::post('{code}/collect', [ReservationOperationsController::class, 'collect'])->name('collect');
    Route::post('{code}/fees/{fee}/resolve', [ReservationOperationsController::class, 'resolve'])->whereNumber('fee')->name('resolve');
    Route::post('{code}/fees/{fee}/counter', [ReservationOperationsController::class, 'moveToCounter'])->whereNumber('fee')->name('counter');
    Route::post('{code}/fees/{fee}/refund', [ReservationOperationsController::class, 'refund'])->whereNumber('fee')->middleware('can:access-admin')->name('refund');
});

Route::prefix('admin')->name('admin.')->middleware(['auth', 'can:access-admin'])->group(function () {
    Route::get('/', DashboardController::class)->name('dashboard');
    Route::prefix('inventory')->name('inventory.')->group(function () {
        Route::get('/', [InventoryController::class, 'index'])->name('index');
        Route::post('enable', [InventoryController::class, 'enable'])->name('enable');
        Route::post('ingredients', [InventoryController::class, 'store'])->name('store');
        Route::get('ingredients/{ingredient}', [InventoryController::class, 'show'])->whereNumber('ingredient')->name('show');
        Route::patch('ingredients/{ingredient}', [InventoryController::class, 'update'])->whereNumber('ingredient')->name('update');
        Route::post('ingredients/{ingredient}/movements', [InventoryController::class, 'record'])->whereNumber('ingredient')->name('record');

        Route::prefix('recipes/{kind}/{option}')->name('recipes.')->where(['kind' => 'size|topping', 'option' => '[0-9]+'])->group(function () {
            Route::get('/', [InventoryRecipeController::class, 'show'])->name('show');
            Route::post('/', [InventoryRecipeController::class, 'store'])->name('store');
            Route::delete('{ingredient}', [InventoryRecipeController::class, 'destroy'])->whereNumber('ingredient')->name('destroy');
        });
    });
    Route::post('counter-orders/{invoice}/status/preview', [CounterOrderController::class, 'preview'])->name('counter-orders.preview');
    Route::post('counter-orders/{invoice}/status', [CounterOrderController::class, 'update'])->name('counter-orders.update');
    Route::get('invoices', [OrderController::class, 'index'])->name('invoices.index');
    Route::get('invoices/{number}', [OrderController::class, 'show'])->name('invoices.show');
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
