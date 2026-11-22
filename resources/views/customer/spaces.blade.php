<x-layouts::customer :full-bleed="true" title="Không gian và chỗ ngồi">
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
<x-theme.seating :options="['endpoint' => route('spaces.snapshot', absolute: false), 'reservationUrl' => route('reservations.create', absolute: false)]" />
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
<a class="btn" href="{{ route('reservations.create') }}">Gửi lời hẹn với Toàn</a>
</div>
</section>
</div>
</x-layouts::customer>
