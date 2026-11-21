<x-layouts::admin title="Size và topping">
    <div class="space-y-6">
        <flux:link :href="route('admin.products.index')">Về danh sách sản phẩm</flux:link>
        <h1 class="text-2xl font-semibold wrap-anywhere">Size và topping của {{ $product->name }}</h1>

        <x-auth-session-status :status="session('status')" />

        <section class="space-y-4">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h2 class="text-lg font-semibold">Size</h2>
                <flux:button variant="primary" :href="route('admin.products.sizes.create', $product)">Thêm size</flux:button>
            </div>
            <ul class="divide-y divide-terrasse-ink/15 rounded-lg border border-terrasse-ink/15 bg-terrasse-paper">
                @forelse ($sizes as $size)
                    <li class="flex flex-wrap items-center justify-between gap-3 p-4">
                        <div class="min-w-0 wrap-anywhere">
                            <div class="font-medium">{{ $size->label }} ({{ $size->code }})</div>
                            <div class="text-sm">{{ number_format($size->price_vnd, 0, ',', '.') }} ₫ · {{ $size->is_active ? 'Đang bán' : 'Ngừng bán' }}</div>
                        </div>
                        <flux:button size="sm" :href="route('admin.products.sizes.edit', [$product, $size])">
                            Chỉnh sửa<span class="sr-only"> size {{ $size->label }}</span>
                        </flux:button>
                    </li>
                @empty
                    <li class="p-4 text-sm">Sản phẩm chưa có size.</li>
                @endforelse
            </ul>
        </section>

        <section class="space-y-4">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h2 class="text-lg font-semibold">Topping đã gắn</h2>
                <flux:link :href="route('admin.toppings.index')">Quản lý topping</flux:link>
            </div>
            <ul class="divide-y divide-terrasse-ink/15 rounded-lg border border-terrasse-ink/15 bg-terrasse-paper">
                @forelse ($toppings as $topping)
                    <li class="flex flex-wrap items-center justify-between gap-3 p-4">
                        <div class="min-w-0 wrap-anywhere">
                            <div class="font-medium">{{ $topping->name }}</div>
                            <div class="text-sm">{{ number_format($topping->price_vnd, 0, ',', '.') }} ₫ · {{ $topping->is_active ? 'Đang bán' : 'Ngừng bán' }}</div>
                        </div>
                        <form method="POST" action="{{ route('admin.products.toppings.destroy', [$product, $topping]) }}">
                            @csrf
                            @method('DELETE')
                            <flux:button size="sm" type="submit">Bỏ gắn<span class="sr-only"> {{ $topping->name }}</span></flux:button>
                        </form>
                    </li>
                @empty
                    <li class="p-4 text-sm">Sản phẩm chưa được gắn topping.</li>
                @endforelse
            </ul>

            @if ($availableToppings->isNotEmpty())
                <form method="POST" action="{{ route('admin.products.toppings.store', $product) }}" class="max-w-md space-y-3">
                    @csrf
                    <flux:select name="topping_id" label="Topping có thể gắn" placeholder="Chọn topping" required>
                        @foreach ($availableToppings as $topping)
                            <flux:select.option :value="$topping->id" :selected="old('topping_id') === (string) $topping->id">
                                {{ $topping->name }} · {{ number_format($topping->price_vnd, 0, ',', '.') }} ₫
                            </flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:button type="submit" variant="primary">Gắn topping</flux:button>
                </form>
            @else
                <p class="text-sm">Hiện chưa có topping đang bán để gắn thêm.</p>
                <flux:error name="topping_id" />
            @endif
        </section>
    </div>
</x-layouts::admin>
