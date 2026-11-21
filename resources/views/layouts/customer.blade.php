@props(['title' => null, 'fullBleed' => false])

<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
    <head>
        @include('partials.head')
    </head>
    <body class="terrasse flex min-h-screen flex-col font-sans antialiased">
        <a href="#main-content" class="sr-only focus:not-sr-only focus:absolute focus:z-10 focus:bg-white focus:p-3">Đến nội dung chính</a>
        <header id="store-header" class="terrasse-store-header" tabindex="-1">
            <nav aria-label="Điều hướng chính" class="terrasse-frame flex flex-wrap items-center justify-between gap-4 py-4">
                <x-terrasse.brand :href="route('home')" />

                <div class="flex flex-wrap items-center gap-x-5 gap-y-2 text-sm font-medium">
                    <a href="{{ route('home') }}" @if (request()->routeIs('home')) aria-current="page" @endif
                        class="terrasse-store-link">Trang chủ</a>
                    <a href="{{ route('menu.index') }}" @if (request()->routeIs('menu.*')) aria-current="true" @endif
                        class="terrasse-store-link">Thực đơn</a>
                    @auth
                        @can('manage-cart')
                            <a href="{{ route('cart.index') }}"
                                @if (request()->routeIs('cart.*')) aria-current="page" @endif
                                class="terrasse-store-link">Giỏ hàng</a>
                        @endcan
                        @can('access-customer-orders')
                            <a href="{{ route('orders.index') }}"
                                @if (request()->routeIs('orders.*')) aria-current="page" @endif
                                class="terrasse-store-link">Đơn của tôi</a>
                        @endcan
                        <a href="{{ route('dashboard') }}" @if (request()->routeIs('dashboard')) aria-current="page" @endif
                            class="terrasse-store-link">Tài khoản</a>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="terrasse-store-link cursor-pointer" data-test="logout-button">Đăng xuất</button>
                        </form>
                    @else
                        <a href="{{ route('login') }}" class="terrasse-store-link">Đăng nhập</a>
                        <a href="{{ route('register') }}" class="terrasse-store-link">Đăng ký</a>
                    @endauth
                </div>
            </nav>
        </header>

        <main id="main-content" class="{{ $fullBleed ? 'flex-1' : 'terrasse-frame flex-1 py-8 sm:py-12' }}">
            {{ $slot }}
        </main>
        <x-terrasse.store-footer />
    </body>
</html>
