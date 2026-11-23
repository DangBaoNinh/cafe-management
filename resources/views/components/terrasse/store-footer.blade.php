<footer class="site-footer french-footer">
    <div class="container footer-grid">
        <div><x-terrasse.brand :href="route('home')" /><p>Một tách cà phê.<br>Một khu vườn. Một chút thảnh thơi.</p></div>
        <nav aria-label="Khám phá cửa hàng"><h2>GHÉ LẠI TOÀN</h2><div class="footer-links">
            <a href="{{ route('menu.index') }}">Thực đơn</a><a href="{{ route('spaces') }}">Không gian &amp; đặt chỗ</a><a href="{{ route('story') }}">Câu chuyện của Toàn</a>
        </div></nav>
        <nav aria-label="Tài khoản"><h2>TÀI KHOẢN</h2><div class="footer-links">
            @auth
                <a href="{{ route('dashboard') }}">Tổng quan tài khoản</a>
                @can('access-customer-orders')<a href="{{ route('orders.index') }}">Đơn của tôi</a><a href="{{ route('reservations.index') }}">Lịch sử đặt bàn</a>@endcan
                @can('manage-delivery-addresses')<a href="{{ route('addresses.index') }}">Địa chỉ giao hàng</a>@endcan
                @can('access-admin')<a href="{{ route('admin.dashboard') }}">Quản trị cửa hàng</a>@endcan
                @can('operate-orders')<a href="{{ route('operations.orders.index') }}">Vận hành quán</a>@endcan
            @else
                <a href="{{ route('login') }}">Đăng nhập</a><a href="{{ route('register') }}">Đăng ký</a>
            @endauth
        </div></nav>
        <div><h2>HẸN NHAU Ở TOÀN</h2><p>Mở cửa 07:00–23:00<br>Nhận lịch đặt bàn 07:00–22:30</p><p>Đặt bàn trước ít nhất 60 phút.</p></div>
    </div>
    <div class="container footer-bottom"><span>© {{ now('Asia/Ho_Chi_Minh')->year }} Toàn Café &amp; Jardin</span><span>Un café. Un jardin. Un moment à soi.</span><a href="#store-header">Về đầu trang</a></div>
</footer>
