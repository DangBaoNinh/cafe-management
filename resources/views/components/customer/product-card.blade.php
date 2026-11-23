@props(['product', 'filters' => [], 'heading' => 'h2'])

<a href="{{ route('menu.show', ['slug' => $product->slug, ...$filters]) }}"
    class="product-card french-product group focus-visible:outline-2 focus-visible:outline-offset-4">
    <div class="product-photo">
        <x-customer.product-image :$product class="size-full" />
        <span class="add-product" aria-hidden="true">+</span>
    </div>

    <div class="product-info flex flex-1 flex-col gap-2">
        <p class="terrasse-muted text-xs font-medium wrap-anywhere">{{ $product->category->name }}</p>
        <{{ $heading }} class="wrap-anywhere group-hover:underline">{{ $product->name }}</{{ $heading }}>
        @if (filled($product->description))
            <p class="product-description line-clamp-2 leading-relaxed wrap-anywhere">{{ $product->description }}</p>
        @endif
        <p class="product-meta mt-auto pt-2">
            <span class="product-price">Từ <x-customer.vnd :amount="(int) $product->starting_price_vnd" /></span>
            <span class="text-xs text-terrasse-muted">Chọn món</span>
        </p>
    </div>
</a>
