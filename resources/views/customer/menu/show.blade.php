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

                <section aria-labelledby="size-heading" class="space-y-2">
                    <h2 id="size-heading" class="text-lg font-semibold">Size và giá</h2>
                    <dl class="divide-y divide-coffee/15 rounded-xl border border-coffee/15 bg-white px-4">
                        @foreach ($sizes as $size)
                            <div class="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1 py-3 text-sm">
                                <dt class="min-w-0 wrap-anywhere">{{ $size->label }}</dt>
                                <dd class="font-medium tabular-nums">{{ number_format($size->price_vnd, 0, ',', '.') }} ₫</dd>
                            </div>
                        @endforeach
                    </dl>
                </section>
            </div>
        </div>
    </article>
</x-layouts::customer>
