<x-layouts::customer :full-bleed="true">

    <section class="jardin-hero" data-film-hero aria-label="Chào mừng đến Toàn">
        <img class="jardin-hero-photo" src="{{ asset('french-theme/assets/film/entry-keyframe.png') }}" alt="Khách bước qua cánh cửa xanh vào khu vườn cà phê đầy nắng và hoa giấy" width="1672" height="941" fetchpriority="high">
        <video class="jardin-film-video" data-hero-film hidden muted loop playsinline preload="none" poster="{{ asset('french-theme/assets/film/entry-keyframe.png') }}" aria-label="Phim ngắn Toàn Café: từ cánh cửa khu vườn đến tách cà phê">
        </video>
        <div class="jardin-hero-shade">
        </div>
        <div class="jardin-hero-content">
            <p class="jardin-kicker">
                <span class="jardin-small-flower">
                    <x-theme.flower />
                </span> Un petit coin de France</p>
            <h1>Lạc vào<br>một miền<br><em>thảnh thơi.</em>
            </h1>
            <p class="jardin-hero-description">Ngoài kia cứ vội.<br>Ở đây có cà phê thơm, hoa nở<br>và một góc dành riêng cho bạn.</p>
            <div class="jardin-hero-actions">
                <a class="jardin-button jardin-button-light" href="{{ route('menu.index') }}" aria-label="Xem thực đơn">Khám phá thực đơn</a>
                <a class="jardin-quiet-link" href="{{ route('reservations.create') }}">Hẹn nhau ở Toàn</a>
            </div>
        </div>
        <div class="jardin-hero-postmark" aria-hidden="true">
            <span>TOÀN CAFÉ & JARDIN</span>
            <x-theme.flower /><i>La vie est belle.</i>
        </div>
        <div class="jardin-hero-bottom">
            <div class="jardin-film-control">
                <button type="button" data-film-toggle hidden aria-pressed="false">Phát phim</button>
                <button type="button" data-foliage-toggle aria-pressed="true">Dừng cây lá đung đưa</button>
                <a href="{{ route('story') }}">Chuyện ở khu vườn</a>
            </div>
            <div class="jardin-film-note">
                <span class="jardin-film-meta" data-film-meta>POUSSEZ LA PORTE · BIENVENUE CHEZ TOÀN</span>
                <p data-film-caption>Bước qua cửa xanh, gặp một khoảng trời riêng.</p>
            </div>
        </div>
        <a class="jardin-scroll" href="#jardin-discover" aria-label="Khám phá thêm">
            <span>CHẬM LẠI MỘT CHÚT</span>
            <span class="jardin-scroll-line">
            </span>
        </a>
    </section>

    <div class="jardin-ribbon" aria-hidden="true">
        <span>Un café.</span>
        <x-theme.flower />
        <span>Un jardin.</span>
        <x-theme.flower />
        <span>Un moment pour soi.</span>
        <x-theme.flower />
        <span>Chez Toàn.</span>
    </div>

    <section class="jardin-intro jardin-container" id="jardin-discover">
        <p class="jardin-kicker">BIENVENUE CHEZ TOÀN</p>
        <div>
            <h2>Không chỉ một tách cà phê.<br>Mà là <em>một khoảng trời riêng.</em>
            </h2>
            <p>Một chút thanh nhã từ những ô cửa Pháp. Một chút thân quen trong hương cà phê Việt. Và thật nhiều khoảng xanh, để mỗi cuộc hẹn ở Toàn đều có lý do để kéo dài thêm.</p>
            <a class="jardin-underlink" href="{{ route('story') }}">Câu chuyện của Toàn</a>
        </div>
        <div class="jardin-intro-flower" aria-hidden="true">
            <x-theme.flower />
            <span>Prendre<br>son temps.</span>
        </div>
    </section>


    @if($bestSellers->isNotEmpty())<section class="jardin-menu jardin-container" aria-labelledby="home-best-sellers-heading">
        <div class="jardin-section-heading">
            <div>
                <p class="jardin-kicker">LES FAVORIS</p>
                <h2 id="home-best-sellers-heading">Món bán chạy</h2>
            </div>
            <a class="jardin-underlink" href="{{ route('menu.index') }}">Xem trọn thực đơn</a>
        </div>
        <ol class="product-grid jardin-product-grid">
            @foreach($bestSellers as $product)<li>
                <x-customer.product-card :$product heading="h3" />
            </li>
            @endforeach</ol>
    </section>
    @endif
    <section class="jardin-menu jardin-container" id="jardin-menu">
        <div class="jardin-section-heading">
            <div>
                <p class="jardin-kicker">Gợi ý từ thực đơn</p>
                <h2>Hôm nay, bạn muốn<br><em>chiều mình bằng gì?</em>
                </h2>
            </div>
            <div class="jardin-menu-note">
                <p>Một ngụm thơm.<br>Một miếng ngọt. Một ngày vui.</p>
                <a class="jardin-underlink" href="{{ route('menu.index') }}">Xem trọn thực đơn</a>
            </div>
        </div>
        <div class="jardin-menu-tabs" role="group" aria-label="Khám phá nhóm món">
            <button type="button" data-home-category="all" aria-pressed="true">Toàn gợi ý</button>
            @foreach($products->pluck('category')->unique('id') as $category)<button type="button" data-home-category="{{ $category->id }}" aria-pressed="false">{{ $category->name }}</button>
            @endforeach</div>
        <div class="product-grid jardin-product-grid" data-home-products>
            @forelse($products as $product)<div data-home-product data-category="{{ $product->category_id }}">
                <x-customer.product-card :$product heading="h3" />
            </div>
            @empty<p>Thực đơn hiện chưa có món đang bán.</p>
            @endforelse</div>
        <p class="jardin-menu-caption" role="status" data-home-menu-caption>Vài hương vị để bắt đầu một cuộc hẹn thật dễ chịu.</p>
    </section>

    <section class="jardin-spaces" id="jardin-spaces">
        <div class="jardin-container">
            <div class="jardin-section-heading">
                <div>
                    <p class="jardin-kicker">LES PETITS COINS · NHỮNG GÓC NHỎ</p>
                    <h2>Góc nào cũng đẹp.<br><em>Chỉ chờ bạn ghé.</em>
                    </h2>
                </div>
                <a class="jardin-underlink" href="{{ route('spaces') }}">Tìm một chỗ ngồi</a>
            </div>
            <div class="jardin-gallery">
                <a href="{{ route('spaces') }}" class="jardin-place jardin-place-garden">
                    <div class="jardin-place-image">
                        <img src="{{ asset('french-theme/assets/garden.webp') }}" alt="Những chiếc bàn trong sân vườn dưới giàn hoa giấy" width="1536" height="1024" loading="lazy">
                        <span class="jardin-photo-label">EN PLEIN AIR</span>
                    </div>
                    <div class="jardin-place-caption">
                        <h3>Dưới giàn hoa</h3>
                        <span>Cho những cuộc hẹn đầy nắng</span>
                    </div>
                </a>
                <a href="{{ route('spaces') }}" class="jardin-place jardin-place-window">
                    <div class="jardin-place-image">
                        <img src="{{ asset('french-theme/assets/interior.webp') }}" alt="Bàn cà phê bên khung cửa xanh nhìn ra khu vườn" width="1122" height="1402" loading="lazy">
                        <span class="jardin-photo-label">PRÈS DE LA FENÊTRE</span>
                    </div>
                    <div class="jardin-place-caption">
                        <h3>Bên khung cửa xanh</h3>
                        <span>Một cuốn sách, một chút bình yên</span>
                    </div>
                </a>
                <a href="{{ route('story') }}" class="jardin-place jardin-place-moment">
                    <div class="jardin-place-image">
                        <img src="{{ asset('french-theme/assets/story.webp') }}" alt="Người khách tận hưởng tách cà phê bên cửa sổ" width="1536" height="1024" loading="lazy">
                        <span class="jardin-photo-label">JUSTE POUR VOUS</span>
                    </div>
                    <div class="jardin-place-caption">
                        <h3>Một khoảng cho mình</h3>
                        <span>Chẳng cần một dịp đặc biệt</span>
                    </div>
                </a>
            </div>
        </div>
    </section>

    <section class="jardin-visit">
        <div class="jardin-container jardin-visit-grid">
            <div class="jardin-visit-copy">
                <p class="jardin-kicker">ENTREZ, C'EST OUVERT</p>
                <h2>Chưa ghé,<br>đã thấy <em>thương.</em>
                </h2>
                <p>Đẩy nhẹ cánh cửa, dạo quanh khu vườn. Khám phá mô hình quán 3D rồi chọn một góc ngồi cho cuộc hẹn sắp tới.</p>
                <div class="jardin-visit-features">
                    <span>
                        <x-theme.icon name="cube" /> Xoay ngắm không gian</span>
                    <span>
                        <x-theme.icon name="table" /> Chọn góc ngồi yêu thích</span>
                </div>
                <a class="jardin-button jardin-button-light" href="{{ route('spaces') }}">Khám phá không gian & bàn</a>
            </div>
            <div class="jardin-visit-visual">
                <span class="jardin-visit-handwriting" aria-hidden="true">Bienvenue au jardin !</span>
                <div class="jardin-visit-art jardin-3d-stage" data-scene-slot>
                    <div class="french-garden-preview">
                        <img src="{{ asset('french-theme/cafe3d/poster.png') }}" alt="Minh họa kiến trúc quán Toàn" loading="lazy">
                        <button type="button" class="btn" data-theme-start-3d>Khám phá quán 3D</button>
                    </div>
                    <div data-garden-host hidden>
                        <x-theme.garden />
                    </div>
                </div>
                <p class="jardin-visit-caption">Một chuyến dạo chơi nho nhỏ, trước cuộc hẹn thật.</p>
            </div>
        </div>
    </section>

    <section class="jardin-ritual jardin-container">
        <div class="jardin-ritual-image">
            <img src="{{ asset('french-theme/assets/ritual.webp') }}" alt="Bàn tay người pha chế rót sữa tạo hình trên tách cà phê" width="1122" height="1402" loading="lazy">
            <span class="jardin-ritual-stamp">Fait avec<br><em>amour.</em>
            </span>
        </div>
        <div class="jardin-ritual-copy">
            <p class="jardin-kicker">LES CHOSES SIMPLES</p>
            <h2>Thương từ những<br><em>điều rất nhỏ.</em>
            </h2>
            <p>Từ hương cà phê vừa đánh thức buổi sáng, đến lớp kem mịn và chiếc bánh bên cạnh. Niềm vui đôi khi chỉ là ngồi xuống, nhấp một ngụm, rồi thấy hôm nay dễ thương hơn một chút.</p>
            <a class="jardin-underlink" href="{{ route('menu.index') }}">Tìm hương vị của bạn</a>
            <span class="jardin-handwriting">Un café, s'il vous plaît.</span>
        </div>
    </section>

    <section class="jardin-invitation">
        <div class="jardin-invitation-flower" aria-hidden="true">
            <x-theme.flower />
        </div>
        <p class="jardin-kicker">ON SE RETROUVE CHEZ TOÀN</p>
        <h2>Hẹn một ngày đẹp trời.<br><em>Hay cứ hôm nay đi.</em>
        </h2>
        <p>Một mình cũng vui. Có bạn càng thương.<br>Toàn dành một góc cho mọi cuộc hẹn.</p>
        <a class="jardin-button" href="{{ route('reservations.create') }}">Giữ một chỗ hẹn</a>
        <span class="jardin-invitation-sign">À bientôt !</span>
    </section>

</x-layouts::customer>
