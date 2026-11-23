<x-layouts::customer :full-bleed="true" title="Câu chuyện của Toàn">
<div class="story-page">
<section class="story-garden-hero">
<img src="{{ asset('french-theme/assets/garden.webp') }}" alt="Khu vườn Toàn Café dưới giàn hoa giấy" width="1536" height="1024" fetchpriority="high">
<div class="story-garden-copy"><p class="eyebrow">L'histoire de Toàn · Le jardin</p><h1>Có những nơi,<br>ta đến chỉ để<br><em>được là chính mình.</em></h1><p>Toàn là lời mời dành cho những ngày bạn muốn sống chậm hơn một nhịp.</p></div>
<button class="story-foliage-control" type="button" data-foliage-toggle aria-pressed="true">Dừng cây lá đung đưa</button>
<p class="story-garden-note">Café. Fleurs. Vous. — Chuyện bắt đầu từ một khu vườn.</p>
</section>
<section class="story-prose container">
<div>
<p class="eyebrow">Une pause, pour vous</p>
<h2>Một khoảng dừng,<br>đủ để lòng<br>
<em>nhẹ hơn.</em>
</h2>
</div>
<div>
<p>Có những buổi chiều, điều ta cần không phải thêm một kế hoạch. Chỉ là một chiếc ghế dễ chịu, tách cà phê còn ấm và một nơi không giục mình phải vội.</p>
<p>Toàn được hình dung như một nơi như thế. Bên ngoài khung cửa xanh, hoa giấy nghiêng theo nắng. Bên trong, tiếng tách chạm đĩa thật khẽ. Bạn có thể mở một cuốn sách, kể tiếp câu chuyện dang dở, hoặc ngồi yên mà chẳng cần giải thích.</p>
<p>Chút thanh nhã của Pháp nằm trong mái hiên, chiếc bàn nhỏ, cách ánh sáng đi qua ô cửa. Sự thân quen nằm ở cảm giác được chào đón, như thể ở đây luôn có một góc vừa vặn cho bạn.</p>
</div>
</section>
<section class="story-pullquote container">
<span aria-hidden="true">“</span>
<blockquote>Chúng ta không nhớ hết<br>những tách cà phê đã uống.<br>Nhưng sẽ nhớ những nơi<br>
<em>khiến mình muốn quay lại.</em>
</blockquote>
</section>
<section class="story-ritual container">
<img src="{{ asset('french-theme/assets/ritual.webp') }}" alt="Bàn tay người pha chế đang hoàn thiện tách latte" loading="lazy">
<div>
<p class="eyebrow">Les choses simples</p>
<h2>Thương từ những<br>
<em>điều rất nhỏ.</em>
</h2>
<p>Một món uống hợp vị. Một chiếc bàn dành cho cuộc hẹn. Một lời chào khiến ngày bớt xa lạ.</p>
<p>Đó là tinh thần Toàn muốn gửi trong từng điểm chạm. Để khi bước ra, bạn mang theo không chỉ hương cà phê, mà còn một chút dịu dàng cho phần còn lại của ngày.</p>
<a class="text-link" href="{{ route('spaces') }}">Tìm góc ngồi của bạn</a>
</div>
</section>
<section class="container story-final">
<p class="eyebrow">On se retrouve chez Toàn</p>
<h2>Hẹn bạn ở Toàn.<br>
<em>Phần còn lại, cứ thong thả.</em>
</h2>
<div class="hero-actions">
<a class="btn" href="{{ route('spaces').'#booking-form' }}">Hẹn một ngày ghé quán</a>
<a class="btn secondary" href="{{ route('menu.index') }}">Chọn một món yêu thích</a>
</div>
</section>
</div>
</x-layouts::customer>
