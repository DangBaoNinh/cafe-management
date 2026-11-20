<section aria-labelledby="drink-options-heading" class="space-y-4">
    <h2 id="drink-options-heading" class="text-lg font-semibold">Tùy chỉnh đồ uống</h2>

    @if ($product === null)
        <p role="alert" class="rounded-lg border border-coffee/15 bg-white p-4 text-sm">Món này hiện không còn bán.</p>
    @else
        <fieldset class="space-y-2">
            <legend class="font-medium">Size và giá</legend>
            <div class="divide-y divide-coffee/15 rounded-xl border border-coffee/15 bg-white">
                @foreach ($product->activeSizes as $size)
                    <label wire:key="drink-size-{{ $size->id }}" class="flex cursor-pointer items-start gap-3 px-4 py-3 text-sm">
                        <input type="radio" name="drink-size" value="{{ $size->id }}" wire:model.live="sizeId"
                            @checked((string) $size->id === $sizeId) class="mt-0.5 size-4 shrink-0 accent-coffee">
                        <span class="min-w-0 flex-1 wrap-anywhere">{{ $size->label }}</span>
                        <span class="shrink-0 font-medium tabular-nums">{{ number_format($size->price_vnd, 0, ',', '.') }} ₫</span>
                    </label>
                @endforeach
            </div>
        </fieldset>

        @if ($product->allows_sugar || $product->allows_ice)
            <div class="grid gap-3 sm:grid-cols-2">
                @foreach ([
                    'sugarLevel' => ['Đường', $product->allows_sugar, $sugarLevel],
                    'iceLevel' => ['Đá', $product->allows_ice, $iceLevel],
                ] as $field => [$label, $enabled, $selected])
                    @if ($enabled)
                        <div class="space-y-2">
                            <label for="drink-{{ $field }}" class="block text-sm font-medium">{{ $label }}</label>
                            <flux:select id="drink-{{ $field }}" wire:model.live="{{ $field }}">
                                @foreach ($levels as $level)
                                    <flux:select.option :value="$level->value" :selected="$level->value === $selected">{{ $level->label() }}</flux:select.option>
                                @endforeach
                            </flux:select>
                        </div>
                    @endif
                @endforeach
            </div>
        @endif

        @if ($product->activeToppings->isNotEmpty())
            <fieldset class="space-y-2">
                <legend class="font-medium">Topping</legend>
                <div class="divide-y divide-coffee/15 rounded-xl border border-coffee/15 bg-white">
                    @foreach ($product->activeToppings as $topping)
                        <label wire:key="drink-topping-{{ $topping->id }}" class="flex cursor-pointer items-start gap-3 px-4 py-3 text-sm">
                            <input type="checkbox" value="{{ $topping->id }}" wire:model.live="toppingIds"
                                @checked(is_array($toppingIds) && in_array((string) $topping->id, $toppingIds, true))
                                class="mt-0.5 size-4 shrink-0 accent-coffee">
                            <span class="min-w-0 flex-1 wrap-anywhere">{{ $topping->name }}</span>
                            <span class="shrink-0 font-medium tabular-nums">+{{ number_format($topping->price_vnd, 0, ',', '.') }} ₫</span>
                        </label>
                    @endforeach
                </div>
            </fieldset>
        @endif

        @if ($messages !== [])
            <div role="alert" class="space-y-3 rounded-lg border border-red-200 bg-white p-4 text-sm text-red-800">
                <ul class="space-y-1">
                    @foreach ($messages as $message)
                        <li>{{ $message }}</li>
                    @endforeach
                </ul>
                <flux:button size="sm" type="button" wire:click="resetSelection">Làm mới lựa chọn</flux:button>
            </div>
        @endif

        <div role="status" class="flex flex-wrap items-center justify-between gap-2 rounded-xl bg-coffee px-4 py-3 text-white">
            <span class="text-sm font-medium">Giá một ly</span>
            <span wire:loading class="text-sm">Đang cập nhật giá…</span>
            <strong wire:loading.remove class="text-lg tabular-nums">
                {{ $quote === null ? '—' : number_format($quote['unit_price_vnd'], 0, ',', '.').' ₫' }}
            </strong>
        </div>

        @can('manage-cart')
            <form wire:submit="addToCart" class="flex flex-wrap items-end gap-3">
                <div class="w-32 space-y-2">
                    <label for="drink-quantity" class="block text-sm font-medium">Số lượng</label>
                    <flux:input id="drink-quantity" type="number" wire:model="quantity"
                        required min="1" :max="\App\Models\CartItem::MAX_QUANTITY" />
                </div>
                <flux:button type="submit" variant="primary" :disabled="$quote === null">Thêm vào giỏ</flux:button>
            </form>
        @endcan
        @guest
            <flux:button type="button" wire:click="addToCart">Đăng nhập để thêm vào giỏ</flux:button>
        @endguest

        @if ($errors->any())
            <ul role="alert" class="space-y-1 rounded-lg border border-red-200 bg-white p-4 text-sm text-red-800">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        @endif
    @endif
</section>
