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
            ['operations.reservations.index', 'operations.reservations.*', 'Lịch đặt bàn và phòng'],
            ['operations.table-sessions.index', 'operations.table-sessions.*', 'Gọi món tại bàn'],
            ['operations.payments.index', 'operations.payments.*', 'Đối chiếu thanh toán'],
            ['operations.invoices.index', 'operations.invoices.index', 'Hóa đơn thanh toán'],
            ['operations.invoices.history', 'operations.invoices.history', 'Lịch sử hóa đơn'],
        ],
        'Cửa hàng' => $canAdminister ? [
            ['admin.categories.index', 'admin.categories.*', 'Quản lý danh mục'],
            ['admin.products.index', 'admin.products.*', 'Quản lý sản phẩm'],
            ['admin.toppings.index', 'admin.toppings.*', 'Quản lý topping'],
            ['admin.inventory.index', 'admin.inventory.*', 'Kho nguyên liệu'],
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
    <body class="terrasse french-theme french-admin antialiased">
        <a href="#main-content" class="sr-only focus:not-sr-only focus:absolute focus:z-10 focus:bg-terrasse-paper focus:p-3">Đến nội dung chính</a>
        <div class="admin-shell">
            <button type="button" class="admin-sidebar-backdrop" data-admin-close aria-label="Đóng điều hướng" tabindex="-1"></button>
            <aside id="admin-navigation" class="admin-sidebar" aria-label="Điều hướng nội bộ">
                <div class="flex items-center justify-between gap-3"><x-terrasse.brand :href="route($workspaceHome)" :caption="$canAdminister ? 'Quản trị' : 'Vận hành'" /><button type="button" class="icon-btn french-sidebar-close" data-admin-close aria-label="Đóng điều hướng"><x-theme.icon name="close" /></button></div>
                @include('layouts.terrasse.internal-nav')
                <div class="sidebar-bottom"><div class="avatar" aria-hidden="true">{{ auth()->user()->initials() }}</div><div class="min-w-0"><strong class="wrap-anywhere">{{ auth()->user()->name }}</strong><span>{{ $canAdminister ? 'Quản trị viên' : 'Nhân viên' }}</span></div></div>
            </aside>
            <div class="admin-main">
                <header class="admin-topbar">
                    <div class="flex min-w-0 items-center gap-3"><button type="button" class="icon-btn admin-menu-toggle" data-admin-toggle aria-label="Mở điều hướng" aria-controls="admin-navigation" aria-expanded="false"><x-theme.icon name="menu" /></button><div class="breadcrumb">Toàn Café &amp; Jardin <span aria-hidden="true"> / </span><strong>{{ $title ?? ($canAdminister ? 'Quản trị' : 'Vận hành') }}</strong></div></div>
                    <a class="french-account-link" href="{{ route('dashboard') }}"><x-theme.icon name="user" /><span>Tài khoản</span></a>
                </header>
                <main id="main-content" class="admin-content min-w-0">{{ $slot }}</main>
            </div>
        </div>
    </body>
</html>
