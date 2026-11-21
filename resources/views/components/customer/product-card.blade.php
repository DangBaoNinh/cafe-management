@props(['product', 'filters' => [], 'heading' => 'h2'])

<a href="{{ route('menu.show', ['slug' => $product->slug, ...$filters]) }}"
    class="terrasse-product-card terrasse-panel group focus-visible:outline-2 focus-visible:outline-offset-4">
    <x-customer.product-image :$product class="aspect-[4/3]" />

    <div class="flex flex-1 flex-col gap-2 p-4 sm:p-5">
        <p class="terrasse-muted text-xs font-medium wrap-anywhere">{{ $product->category->name }}</p>
        <{{ $heading }} class="wrap-anywhere group-hover:underline">{{ $product->name }}</{{ $heading }}>
        @if (filled($product->description))
            <p class="terrasse-muted line-clamp-2 text-sm leading-relaxed wrap-anywhere">{{ $product->description }}</p>
        @endif
        <p class="mt-auto flex items-center justify-between gap-2 pt-2 text-sm font-semibold text-accent">
            <span>Từ <x-customer.vnd :amount="(int) $product->starting_price_vnd" /></span>
            <flux:icon.arrow-up-right class="size-4 shrink-0" aria-hidden="true" />
        </p>
    </div>
</a>
