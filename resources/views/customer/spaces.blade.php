<x-layouts::customer :full-bleed="true" title="Không gian & đặt chỗ">
<div>
<section class="space-intro container">
<div>
<p class="eyebrow">Votre petit coin de bonheur</p>
<h1>Chọn một góc ngồi.<br>
<em>Để ngày thật êm.</em>
</h1>
</div>
<p>Bên khung cửa, dưới tán hoa hay một góc dành cho riêng mình. Ngắm không gian và tìm chiếc bàn hợp với cuộc hẹn của bạn.</p>
</section>
<x-theme.seating :options="['endpoint' => route('spaces.snapshot', absolute: false), 'reservationUrl' => route('spaces', absolute: false).'#booking-form']" />
<section id="booking-form" class="container space-y-5 py-8" style="scroll-margin-top: 6rem">
    <header><p class="eyebrow">Hẹn gặp tại Toàn</p><h2>Thông tin đặt bàn</h2><p>Chọn bàn trên sơ đồ, giờ đến và số khách. Bắt buộc chuyển khoản cọc: bàn 100.000 ₫, phòng họp 10 người 300.000 ₫.</p></header>
    @include('reservations.feedback')
    @can('access-customer-orders')
        @if($preferredSeat || $searched)
            @include('customer.reservations.form')
        @else
            <details class="terrasse-panel rounded p-4"><summary class="cursor-pointer font-semibold">Xác nhận đặt bàn</summary>@include('customer.reservations.form')</details>
        @endif
    @else
        @if($preferredSeat)<p>Chỗ đã chọn: <strong>{{ $preferredSeat->code }}</strong> · Tầng {{ $preferredSeat->floor }} · {{ $preferredSeat->capacity }} người.</p>@endif
        <p>Đăng nhập tài khoản khách hàng để đặt chỗ và thanh toán cọc.</p>
        <flux:button :href="route('reservations.create', request()->only(['seat_id', 'party_size', 'booking_date']))" variant="primary">Đăng nhập để đặt chỗ</flux:button>
    @endcan
</section>
<section class="space-portraits container">
<article>
<img src="{{ asset('french-theme/assets/garden.webp') }}" alt="Giàn hoa giấy bên những chiếc bàn ngoài hiên" loading="lazy">
<div>
<p class="eyebrow">Sous les fleurs</p>
<h2>Dưới tán hoa.</h2>
<p>Một khoảng nắng vừa đủ, vài câu chuyện kéo dài. Góc hiên dành cho những cuộc gặp muốn ở lại thêm.</p>
</div>
</article>
<article>
<img src="{{ asset('french-theme/assets/interior.webp') }}" alt="Bàn bên khung cửa Pháp" loading="lazy">
<div>
<p class="eyebrow">Près de la fenêtre</p>
<h2>Bên ô cửa xanh.</h2>
<p>Cho cuốn sách đang đọc dở, một người bạn lâu ngày và khoảng lặng thật dễ chịu.</p>
</div>
</article>
</section>
<section class="container">
<div class="reservation-banner">
<p class="eyebrow">À bientôt</p>
<h2>Góc ngồi đã có.<br>Còn chờ <em>cuộc hẹn của bạn.</em>
</h2>
<a class="btn" href="{{ route('spaces') }}#booking-form">Gửi lời hẹn với Toàn</a>
</div>
</section>
</div>
</x-layouts::customer>
