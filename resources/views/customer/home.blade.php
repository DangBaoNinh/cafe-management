<x-layouts::customer :full-bleed="true">
    <section class="terrasse-hero" aria-labelledby="home-heading">
        <div class="terrasse-frame terrasse-hero-copy">
            <p class="terrasse-hero-kicker">Cà phê theo phong cách Paris</p>
            <h1 id="home-heading">Một góc <em>Paris.</em></h1>
            <p class="terrasse-hero-description">Dành một chút thời gian cho ly cà phê yêu thích, như ngồi bên hiên quán một sáng ở Paris.</p>
            <a href="{{ route('menu.index') }}" class="terrasse-hero-cta">
                Xem thực đơn <flux:icon.arrow-up-right class="size-4" aria-hidden="true" />
            </a>
        </div>
    </section>

    @if ($bestSellers->isNotEmpty())
        <section class="terrasse-frame py-9 sm:py-14" aria-labelledby="home-best-sellers-heading">
            <h2 id="home-best-sellers-heading" class="terrasse-display mb-5 text-4xl font-semibold sm:text-6xl">Món bán chạy</h2>
            <ol class="grid list-none gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($bestSellers as $product)
                    <li class="min-w-0">
                        <x-customer.product-card :$product heading="h3" />
                    </li>
                @endforeach
            </ol>
        </section>
    @endif

    @if ($products->isNotEmpty())
        <section class="terrasse-frame py-9 sm:py-14" aria-labelledby="home-menu-heading">
            <header class="mb-5 flex flex-wrap items-baseline justify-between gap-x-6 gap-y-2">
                <h2 id="home-menu-heading" class="terrasse-display text-4xl font-semibold sm:text-6xl">Gợi ý từ thực đơn</h2>
                <a href="{{ route('menu.index') }}" class="terrasse-store-link inline-flex items-center gap-2 text-sm font-semibold text-accent">
                    Xem tất cả <flux:icon.arrow-up-right class="size-4" aria-hidden="true" />
                </a>
            </header>
            <ul class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($products as $product)
                    <li class="min-w-0">
                        <x-customer.product-card :$product heading="h3" />
                    </li>
                @endforeach
            </ul>
        </section>
    @endif
</x-layouts::customer>
