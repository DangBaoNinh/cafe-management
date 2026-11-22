@props(['title' => null, 'fullBleed' => false, 'settings' => false])
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    @include('partials.head')
    @stack('styles')
</head>
<body @class(['terrasse french-theme french-public flex min-h-screen flex-col antialiased', 'jardin-page' => request()->routeIs('home'), 'jardin-story-page' => request()->routeIs('story')])>
    <a href="#main-content" class="jardin-skip">Đi đến nội dung</a>
    <div class="jardin-announcement"><span>Café. Fleurs. Et vous.</span><span>Một chút Pháp, một chút bình yên.</span><a href="{{ route('spaces') }}">Khám phá khu vườn</a></div>
    <div class="jardin-header">
        <header id="store-header" class="site-header container" tabindex="-1">
            <x-terrasse.brand :href="route('home')" />
            <nav class="site-nav" id="public-nav" aria-label="Điều hướng chính">
                @foreach ([['home', 'home', 'Trang chủ'], ['menu.index', 'menu.*', 'Thực đơn'], ['spaces', 'spaces', 'Không gian'], ['story', 'story', 'Câu chuyện'], ['reservations.create', 'reservations.*', 'Đặt chỗ']] as [$route, $pattern, $label])
                    <a href="{{ route($route) }}" @if(request()->routeIs($pattern)) aria-current="page" @endif @class(['active' => request()->routeIs($pattern)])>{{ $label }}</a>
                @endforeach
                @can('access-customer-orders')<a href="{{ route('orders.index') }}" @if(request()->routeIs('orders.*')) aria-current="page" @endif>Đơn của tôi</a>@endcan
                @auth<a href="{{ route('support.index') }}" @if(request()->routeIs('support.*')) aria-current="page" @endif>Liên hệ</a>@endauth
                @auth
                    <form method="POST" action="{{ route('logout') }}" class="french-mobile-logout">@csrf<button type="submit">Đăng xuất</button></form>
                @else
                    <a class="french-mobile-register" href="{{ route('register') }}">Đăng ký</a>
                @endauth
            </nav>
            <div class="nav-tools">
                @can('manage-cart')<a class="icon-btn bag" href="{{ route('cart.index') }}" aria-label="Giỏ hàng"><x-theme.icon name="bag" /></a>@endcan
                @auth
                    <a class="french-account-link" href="{{ route('dashboard') }}" @if(request()->routeIs('dashboard') || $settings) aria-current="page" @endif aria-label="Tài khoản"><x-theme.icon name="user" /><span>Tài khoản</span></a>
                    <form method="POST" action="{{ route('logout') }}" class="french-logout">@csrf<button type="submit" data-test="logout-button">Đăng xuất</button></form>
                @else
                    <a class="french-account-link" href="{{ route('login') }}">Đăng nhập</a>
                    <a class="btn small french-register" href="{{ route('register') }}">Đăng ký</a>
                @endauth
                <button class="icon-btn mobile-menu" type="button" data-public-nav aria-label="Mở điều hướng" aria-expanded="false" aria-controls="public-nav"><x-theme.icon name="menu" /></button>
            </div>
        </header>
    </div>
    <main id="main-content" class="{{ $fullBleed ? 'flex-1 min-w-0' : 'terrasse-frame french-page flex-1 min-w-0 py-8 sm:py-12' }}">{{ $slot }}</main>
    <x-terrasse.store-footer />
    <x-terrasse.storefront-chat />
    @if ($settings)
        @persist('toast')<flux:toast.group><flux:toast /></flux:toast.group>@endpersist
        @fluxScripts
    @endif
    @stack('scripts')
</body>
</html>
