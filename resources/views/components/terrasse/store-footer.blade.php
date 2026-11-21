<footer class="terrasse-store-footer">
    <div class="terrasse-frame">
        <div class="grid grid-cols-2 gap-8 py-9 sm:py-12 lg:grid-cols-[minmax(0,1.8fr)_repeat(2,minmax(0,1fr))]">
            <div class="col-span-2 lg:col-span-1">
                <x-terrasse.brand :href="route('home')" />
                <p class="terrasse-footer-muted mt-4 max-w-xs text-sm leading-relaxed">
                    Chọn ly cà phê hợp gu, nhận tại quán hoặc giao tận nơi.
                </p>
            </div>

            <nav aria-labelledby="footer-store-title">
                <h2 id="footer-store-title" class="terrasse-display text-2xl font-semibold">Cửa hàng</h2>
                <ul class="mt-3 space-y-2 text-sm">
                    <li><a href="{{ route('home') }}" class="terrasse-footer-link">Trang chủ</a></li>
                    <li><a href="{{ route('menu.index') }}" class="terrasse-footer-link">Thực đơn</a></li>
                    @can('manage-cart')
                        <li><a href="{{ route('cart.index') }}" class="terrasse-footer-link">Giỏ hàng</a></li>
                    @endcan
                </ul>
            </nav>

            <nav aria-labelledby="footer-account-title">
                <h2 id="footer-account-title" class="terrasse-display text-2xl font-semibold">Tài khoản</h2>
                <ul class="mt-3 space-y-2 text-sm">
                    @auth
                        <li><a href="{{ route('dashboard') }}" class="terrasse-footer-link">Tổng quan tài khoản</a></li>
                        @can('access-customer-orders')
                            <li><a href="{{ route('orders.index') }}" class="terrasse-footer-link">Đơn của tôi</a></li>
                        @endcan
                        @can('manage-delivery-addresses')
                            <li><a href="{{ route('addresses.index') }}" class="terrasse-footer-link">Địa chỉ giao hàng</a></li>
                        @endcan
                    @else
                        <li><a href="{{ route('login') }}" class="terrasse-footer-link">Đăng nhập</a></li>
                        <li><a href="{{ route('register') }}" class="terrasse-footer-link">Đăng ký</a></li>
                    @endauth
                </ul>
            </nav>
        </div>

        <div class="terrasse-footer-bottom flex flex-wrap items-center justify-between gap-3 py-4 text-xs">
            <p class="terrasse-footer-muted wrap-anywhere">© {{ now('Asia/Ho_Chi_Minh')->year }} {{ config('app.name') }}</p>
            <a href="#store-header" class="terrasse-footer-link inline-flex items-center gap-2 font-medium">
                Về đầu trang <flux:icon.arrow-up class="size-3.5" aria-hidden="true" />
            </a>
        </div>
    </div>
</footer>
