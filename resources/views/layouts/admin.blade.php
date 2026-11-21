<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-cream font-sans text-coffee antialiased">
        <header class="bg-coffee text-white">
            <nav aria-label="Điều hướng cửa hàng" class="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-4 px-5 py-4 sm:px-8">
                @can('access-admin')
                    <a href="{{ route('admin.dashboard') }}" class="font-semibold">{{ config('app.name') }} · Quản trị</a>
                @else
                    <a href="{{ route('operations.orders.index') }}" class="font-semibold">{{ config('app.name') }} · Vận hành</a>
                @endcan
                <div class="flex flex-wrap gap-4 text-sm">
                    <a href="{{ route('operations.orders.index') }}" class="hover:underline">Đơn chờ xác nhận</a>
                    <a href="{{ route('operations.preparation.index') }}" class="hover:underline">Pha chế</a>
                    <a href="{{ route('operations.handoff.index') }}" class="hover:underline">Bàn giao</a>
                    <a href="{{ route('operations.payments.index') }}" class="hover:underline">Đối chiếu thanh toán</a>
                    @can('access-admin')
                        <a href="{{ route('admin.accounts.index') }}" class="hover:underline">Quản lý tài khoản</a>
                        <a href="{{ route('admin.categories.index') }}" class="hover:underline">Quản lý danh mục</a>
                        <a href="{{ route('admin.products.index') }}" class="hover:underline">Quản lý sản phẩm</a>
                        <a href="{{ route('admin.toppings.index') }}" class="hover:underline">Quản lý topping</a>
                        <a href="{{ route('admin.delivery-settings.edit') }}" class="hover:underline">Cấu hình giao hàng</a>
                        <a href="{{ route('admin.coupons.index') }}" class="hover:underline">Mã giảm giá</a>
                    @endcan
                    <a href="{{ route('home') }}" class="hover:underline">Về trang chủ</a>
                </div>
            </nav>
        </header>

        <main class="mx-auto max-w-6xl px-5 py-8 sm:px-8">
            {{ $slot }}
        </main>
    </body>
</html>
