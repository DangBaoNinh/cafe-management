<x-layouts::customer :title="$product->name">
    <article class="space-y-6">
        <flux:link :href="route('menu.index', $filters)">Về thực đơn</flux:link>

        <div class="grid items-start gap-8 md:grid-cols-2 md:gap-10">
            <x-customer.product-image :$product detail class="aspect-[4/3] rounded-xl border border-coffee/15" />

            <div class="min-w-0 space-y-5">
                <header class="space-y-3">
                    <flux:link :href="route('menu.index', ['category' => $product->category->slug])">
                        <span class="wrap-anywhere">{{ $product->category->name }}</span>
                    </flux:link>
                    <h1 class="text-3xl font-semibold tracking-tight wrap-anywhere sm:text-4xl">{{ $product->name }}</h1>
                    <p class="text-xl font-semibold">Từ {{ number_format($sizes->first()->price_vnd, 0, ',', '.') }} ₫</p>
                </header>

                @if (filled($product->description))
                    <p class="whitespace-pre-line leading-relaxed wrap-anywhere">{{ $product->description }}</p>
                @endif

                <livewire:drink-customizer :product="$product" />
            </div>
        </div>
    </article>
</x-layouts::customer>
