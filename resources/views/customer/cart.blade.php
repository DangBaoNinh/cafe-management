<x-layouts::customer title="Giỏ hàng">
    <div class="space-y-6">
        <header class="flex flex-wrap items-baseline justify-between gap-3">
            <h1 class="text-3xl font-semibold tracking-tight sm:text-4xl">Giỏ hàng</h1>
            <flux:link :href="route('menu.index')">Về thực đơn</flux:link>
        </header>

        @if (session('status'))
            <p role="status" class="rounded-lg border border-terrasse-ink/15 bg-terrasse-paper p-4 text-sm">{{ session('status') }}</p>
        @endif

        @if ($errors->any())
            <ul role="alert" class="space-y-1 rounded-lg border border-terrasse-danger/30 bg-terrasse-paper p-4 text-sm text-terrasse-danger">
                @foreach ($errors->all() as $message)
                    <li>{{ $message }}</li>
                @endforeach
            </ul>
        @endif

        <div class="french-cart-layout">
        <section aria-label="Món trong giỏ">
        @if ($lines === [])
            <p class="rounded-xl border border-terrasse-ink/15 bg-terrasse-paper p-5">Giỏ hàng của bạn đang trống.</p>
        @else
            <ul class="divide-y divide-terrasse-ink/15 rounded-xl border border-terrasse-ink/15 bg-terrasse-paper">
                @foreach ($lines as ['item' => $item, 'unitPrice' => $unitPrice, 'total' => $total])
                    @php($product = $item->size->product)
                    <li class="space-y-3 p-4 sm:p-5">
                        <div class="flex items-start gap-4">
                        <x-customer.product-image :$product class="french-cart-photo" />
                        <div class="min-w-0 flex-1 space-y-3">
                        <div class="flex flex-wrap justify-between gap-x-5 gap-y-2">
                            <div class="min-w-0 space-y-1 wrap-anywhere">
                                <h2 class="font-semibold">{{ $product->name }}</h2>
                                <p class="text-sm">{{ $item->size->label }}</p>
                                @foreach (['Đường' => $item->sugar_level, 'Đá' => $item->ice_level] as $label => $level)
                                    @if ($level !== null)
                                        <p class="text-sm">{{ $label }} · {{ \App\Enums\DrinkLevel::from($level)->label() }}</p>
                                    @endif
                                @endforeach
                                @if ($item->toppings->isNotEmpty())
                                    <p class="text-sm">Topping · {{ $item->toppings->pluck('name')->join(', ') }}</p>
                                @endif
                            </div>
                            @if ($total !== null)
                                <div class="space-y-1 text-right tabular-nums">
                                    <p class="font-semibold">{{ number_format($total, 0, ',', '.') }} ₫</p>
                                    <p class="text-sm">{{ number_format($unitPrice, 0, ',', '.') }} ₫/ly</p>
                                </div>
                            @endif
                        </div>

                        <div class="flex flex-wrap items-end gap-3">
                            @if ($total === null)
                                <div class="space-y-1 text-sm">
                                    <p>Số lượng · {{ $item->quantity }}</p>
                                    <p class="text-terrasse-danger">Món hoặc tùy chọn đã ngừng bán hoặc thay đổi. Vui lòng xóa dòng này và xem lại thực đơn.</p>
                                </div>
                            @else
                                <form method="POST" action="{{ route('cart.update', $item) }}" class="flex flex-wrap items-end gap-3">
                                    @csrf
                                    @method('PATCH')
                                    <input type="hidden" name="version" value="{{ $version }}">
                                    <div class="w-32 space-y-2">
                                        <label for="cart-quantity-{{ $item->id }}" class="block text-sm font-medium">Số lượng</label>
                                        <flux:input id="cart-quantity-{{ $item->id }}" name="quantity" type="number"
                                            :value="$item->quantity" required min="1" :max="\App\Models\CartItem::MAX_QUANTITY" />
                                    </div>
                                    <flux:button type="submit">Cập nhật</flux:button>
                                </form>
                            @endif
                            <form method="POST" action="{{ route('cart.destroy', $item) }}">
                                @csrf
                                @method('DELETE')
                                <input type="hidden" name="version" value="{{ $version }}">
                                <flux:button type="submit" :aria-label="'Xóa '.$product->name.' khỏi giỏ'">Xóa</flux:button>
                            </form>
                        </div>
                        </div>
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
        </section>

        <aside class="french-cart-summary space-y-5" aria-label="Tóm tắt giỏ hàng">
        <p class="eyebrow">Votre commande</p>
        <h2 class="terrasse-display text-3xl">Cuộc hẹn có gì?</h2>
        <div class="space-y-2 border-y border-terrasse-ink/15 py-5">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <span class="font-medium">Tạm tính</span>
                <strong class="text-lg tabular-nums">{{ number_format($subtotal, 0, ',', '.') }} ₫</strong>
            </div>
            @if (in_array(null, array_column($lines, 'total'), true))
                <p class="text-sm">Tạm tính chỉ gồm các món và tùy chọn còn bán.</p>
            @endif
        </div>

        <div class="flex flex-col items-start gap-4">
            <flux:link :href="route('cart.delivery')">Xem phí giao hàng và nhận tại quán</flux:link>
            @if ($lines !== [] && ! in_array(null, array_column($lines, 'total'), true))
                <flux:button variant="primary" :href="route('checkout.create')">Tiến hành đặt hàng</flux:button>
            @endif
        </div>
        <p class="terrasse-muted text-sm">Phí giao hàng và mã giảm giá được tính khi xác nhận đặt hàng.</p>
        </aside>
        </div>
    </div>
</x-layouts::customer>
