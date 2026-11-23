@php
    $keyword = $filterInput['q'] ?? '';
    $minPrice = $filterInput['min_price'] ?? '';
    $maxPrice = $filterInput['max_price'] ?? '';
    $sort = $filterInput['sort'] ?? 'name';
@endphp

<div class="space-y-4 rounded-xl border border-terrasse-ink/15 bg-terrasse-paper p-4 sm:p-5">
    @if ($filterErrors)
        <ul role="alert" class="list-inside list-disc space-y-1 text-sm text-terrasse-danger">
            @foreach ($filterErrors->all() as $message)
                <li>{{ $message }}</li>
            @endforeach
        </ul>
    @endif

    <form method="GET" action="{{ route('menu.index') }}" aria-label="Tìm và lọc món" class="space-y-4">
        @if ($selectedCategory)
            <input type="hidden" name="category" value="{{ $selectedCategory->slug }}">
        @endif

        <div class="grid items-start gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div class="space-y-2">
                <label for="menu-keyword" class="block text-sm font-medium">Tên món</label>
                <flux:input id="menu-keyword" name="q" type="search" placeholder="Ví dụ Cà phê sữa"
                    maxlength="100" :value="is_string($keyword) ? $keyword : ''" :invalid="$filterErrors?->has('q')" />
            </div>
            <div class="space-y-2">
                <label for="menu-min-price" class="block text-sm font-medium">Giá từ (VND)</label>
                <flux:input id="menu-min-price" name="min_price" type="number" min="0" max="4294967295" step="1"
                    :value="is_scalar($minPrice) ? $minPrice : ''" :invalid="$filterErrors?->has('min_price')" />
            </div>
            <div class="space-y-2">
                <label for="menu-max-price" class="block text-sm font-medium">Giá đến (VND)</label>
                <flux:input id="menu-max-price" name="max_price" type="number" min="0" max="4294967295" step="1"
                    :value="is_scalar($maxPrice) ? $maxPrice : ''" :invalid="$filterErrors?->has('max_price')" />
            </div>
            <div class="min-w-0 space-y-2">
                <label for="menu-sort" class="block text-sm font-medium">Sắp xếp</label>
                <flux:select id="menu-sort" name="sort" :invalid="$filterErrors?->has('sort')">
                    @foreach ($sortOptions as $option => $label)
                        <flux:select.option :value="$option === 'name' ? '' : $option"
                            :selected="$sort === $option">{{ $label }}</flux:select.option>
                    @endforeach
                </flux:select>
            </div>
        </div>

        <div class="flex flex-wrap items-center gap-4">
            <flux:button type="submit" variant="primary">Áp dụng</flux:button>
            @if ($filters !== [] || $filterErrors)
                <flux:link :href="route('menu.index')">Xóa tất cả bộ lọc</flux:link>
            @endif
        </div>
    </form>

    @php($removableFilters = array_intersect_key(['q' => 'từ khóa', 'min_price' => 'giá từ', 'max_price' => 'giá đến', 'sort' => 'sắp xếp'], $filters))
    @if ($removableFilters !== [])
        <ul aria-label="Bộ lọc đang áp dụng" class="flex flex-wrap gap-x-4 gap-y-2 text-sm">
            @foreach ($removableFilters as $key => $label)
                <li><flux:link :href="route('menu.index', Arr::except($filters, [$key]))">Bỏ {{ $label }}</flux:link></li>
            @endforeach
        </ul>
    @endif
</div>
