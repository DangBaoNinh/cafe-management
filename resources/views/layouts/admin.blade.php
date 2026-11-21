@props(['title' => null])

@php
    $canAdminister = auth()->user()?->can('access-admin');
    $workspaceHome = $canAdminister ? 'admin.dashboard' : 'operations.orders.index';
    $sections = [
        'Quản trị' => $canAdminister ? [
            ['admin.dashboard', 'admin.dashboard', 'Tổng quan quản trị'],
            ['admin.orders.index', 'admin.orders.*', 'Quản lý đơn hàng'],
            ['admin.customers.index', 'admin.customers.*', 'Tra cứu khách hàng'],
            ['admin.accounts.index', 'admin.accounts.*', 'Quản lý tài khoản'],
        ] : [],
        'Vận hành' => [
            ['operations.orders.index', 'operations.orders.*', 'Đơn chờ xác nhận'],
            ['operations.preparation.index', 'operations.preparation.*', 'Pha chế'],
            ['operations.handoff.index', 'operations.handoff.*', 'Bàn giao'],
            ['operations.pickups.index', 'operations.pickups.*', 'Lịch nhận tại quán'],
            ['operations.payments.index', 'operations.payments.*', 'Đối chiếu thanh toán'],
        ],
        'Cửa hàng' => $canAdminister ? [
            ['admin.categories.index', 'admin.categories.*', 'Quản lý danh mục'],
            ['admin.products.index', 'admin.products.*', 'Quản lý sản phẩm'],
            ['admin.toppings.index', 'admin.toppings.*', 'Quản lý topping'],
            ['admin.delivery-settings.edit', 'admin.delivery-settings.*', 'Cấu hình giao hàng'],
            ['admin.coupons.index', 'admin.coupons.*', 'Mã giảm giá'],
        ] : [],
    ];
@endphp

<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
    <head>
        @include('partials.head')
    </head>
    <body class="terrasse font-sans antialiased">
        <a href="#main-content" class="sr-only focus:not-sr-only focus:absolute focus:z-10 focus:bg-terrasse-paper focus:p-3">Đến nội dung chính</a>
        <div class="terrasse-workspace">
            <aside class="terrasse-sidebar min-w-0 px-4 py-4 lg:px-4 lg:py-7">
                <x-terrasse.brand :href="route($workspaceHome)" :caption="$canAdminister ? 'Quản trị' : 'Vận hành'" class="mb-4 ms-1 lg:mb-7" />
                <div class="hidden lg:block">
                    @include('layouts.terrasse.internal-nav')
                </div>
                <details class="lg:hidden">
                    <summary class="cursor-pointer rounded border border-white/25 px-3 py-2 text-sm font-semibold">Điều hướng nội bộ</summary>
                    <div class="mt-3">
                        @include('layouts.terrasse.internal-nav')
                    </div>
                </details>
            </aside>

            <main id="main-content" class="mx-auto w-full min-w-0 max-w-screen-2xl px-4 py-7 sm:px-8 lg:px-9 lg:py-10">
                {{ $slot }}
            </main>
        </div>
    </body>
</html>
