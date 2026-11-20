<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
    <head>
        @include('partials.head')
    </head>
    <body class="flex min-h-screen flex-col bg-cream font-sans text-coffee antialiased">
        <header class="border-b border-coffee/15 bg-white">
            <nav aria-label="Điều hướng chính" class="mx-auto flex max-w-5xl flex-wrap items-center justify-between gap-4 px-5 py-4 sm:px-8">
                <a href="{{ route('home') }}" class="text-lg font-semibold">{{ config('app.name') }}</a>

                <div class="flex flex-wrap items-center gap-x-5 gap-y-2 text-sm font-medium">
                    <a href="{{ route('menu.index') }}"
                        @if (request()->routeIs('menu.*')) aria-current="true" @endif
                        class="hover:underline aria-[current=true]:underline">Thực đơn</a>
                    @auth
                        @can('manage-cart')
                            <a href="{{ route('cart.index') }}"
                                @if (request()->routeIs('cart.*')) aria-current="page" @endif
                                class="hover:underline aria-[current=page]:underline">Giỏ hàng</a>
                        @endcan
                        @can('view-own-orders')
                            <a href="{{ route('orders.index') }}"
                                @if (request()->routeIs('orders.*')) aria-current="page" @endif
                                class="hover:underline aria-[current=page]:underline">Đơn của tôi</a>
                        @endcan
                        <a href="{{ route('dashboard') }}" class="hover:underline">Tài khoản</a>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="cursor-pointer hover:underline">Đăng xuất</button>
                        </form>
                    @else
                        <a href="{{ route('login') }}" class="hover:underline">Đăng nhập</a>
                        <a href="{{ route('register') }}" class="hover:underline">Đăng ký</a>
                    @endauth
                </div>
            </nav>
        </header>

        <main class="mx-auto w-full max-w-5xl flex-1 px-5 py-16 sm:px-8 sm:py-24">
            {{ $slot }}
        </main>
    </body>
</html>
