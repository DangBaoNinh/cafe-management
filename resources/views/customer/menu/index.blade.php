<x-layouts::customer title="Thực đơn">
    <section class="space-y-8">
        <h1 class="text-3xl font-semibold tracking-tight sm:text-5xl">Thực đơn</h1>

        @if ($categories->isNotEmpty())
            <nav aria-label="Danh mục thực đơn" class="flex flex-wrap gap-2">
                <a href="{{ route('menu.index', Arr::except($filters, ['category'])) }}"
                    @if ($selectedCategory === null) aria-current="page" @endif
                    @class(['rounded-full border px-4 py-2 text-sm font-medium', 'border-coffee bg-coffee text-white' => $selectedCategory === null, 'border-coffee/15 bg-white hover:border-coffee/50' => $selectedCategory !== null])>
                    Tất cả
                </a>
                @foreach ($categories as $category)
                    @php($isSelected = $selectedCategory?->is($category) ?? false)
                    <a href="{{ route('menu.index', [...$filters, 'category' => $category->slug]) }}"
                        @if ($isSelected) aria-current="page" @endif
                        @class(['max-w-full wrap-anywhere rounded-full border px-4 py-2 text-sm font-medium', 'border-coffee bg-coffee text-white' => $isSelected, 'border-coffee/15 bg-white hover:border-coffee/50' => ! $isSelected])>
                        {{ $category->name }}
                    </a>
                @endforeach
            </nav>
        @endif

        @include('customer.menu.filters')

        @if ($products?->isNotEmpty())
            <ul class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($products as $product)
                    <li class="min-w-0">
                        <x-customer.product-card :$product :filters="$detailFilters" />
                    </li>
                @endforeach
            </ul>
        @elseif ($products !== null)
            <div class="space-y-3 rounded-xl border border-coffee/15 bg-white p-6">
                @if ($products->total() > 0)
                    <p>Trang này không có món.</p>
                    <flux:link :href="$products->url(1)">Về trang đầu</flux:link>
                @elseif (isset($filters['q']) || isset($filters['min_price']) || isset($filters['max_price']))
                    <p>Không tìm thấy món phù hợp. Hãy thử từ khóa khác hoặc mở rộng khoảng giá.</p>
                @elseif ($selectedCategory)
                    <p>Danh mục này chưa có món đang bán.</p>
                    <flux:link :href="route('menu.index')">Xem tất cả món</flux:link>
                @else
                    <p>Thực đơn hiện chưa có món đang bán.</p>
                @endif
            </div>
        @endif

        {{ $products?->onEachSide(1)->links() }}
    </section>
</x-layouts::customer>
