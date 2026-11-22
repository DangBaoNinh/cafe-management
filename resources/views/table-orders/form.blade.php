@use('App\Enums\DrinkLevel')
@use('App\Models\CartItem')
@use('Illuminate\Support\Str')

@if ($products->isEmpty())
    <p class="text-sm">Hiện chưa có món đang bán.</p>
@else
    <form method="POST" action="{{ $action }}" data-table-order class="space-y-4">
        @csrf
        <input type="hidden" name="session_reference" value="{{ $tableSession->reference }}">
        <input type="hidden" name="submission_key" value="{{ old('submission_key') ?: Str::uuid() }}">
        <div data-order-lines data-previous-lines="{{ json_encode(old('lines', [])) }}" class="space-y-3"></div>
        <div class="flex flex-wrap items-center gap-3">
            <flux:button type="button" data-add-order-line>Thêm dòng món</flux:button>
            <flux:button type="submit" variant="primary">{{ $submitLabel }}</flux:button>
        </div>
        <noscript><p>Cần bật JavaScript để chọn món và gửi lượt gọi.</p></noscript>

        <template data-order-line-template>
            <fieldset data-order-line class="space-y-3 rounded-lg border border-terrasse-ink/15 p-3">
                <legend data-order-line-label class="px-2 text-sm font-semibold"></legend>
                <div class="grid gap-3 sm:grid-cols-[minmax(0,1fr)_7rem_auto] sm:items-end">
                    <label class="grid gap-1 text-sm">
                        Món
                        <select data-field="product_id" class="w-full rounded-lg border border-terrasse-ink/30 bg-terrasse-paper p-2" required>
                            <option value="">Chọn món</option>
                            @foreach ($products as $product)
                                <option value="{{ $product->id }}">{{ $product->name }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="grid gap-1 text-sm">
                        Số ly
                        <input data-field="quantity" type="number" min="1" max="{{ CartItem::MAX_QUANTITY }}" step="1" value="1"
                            class="w-full rounded-lg border border-terrasse-ink/30 bg-terrasse-paper p-2" required>
                    </label>
                    <button type="button" data-remove-order-line class="justify-self-start rounded-lg border border-terrasse-ink/30 px-3 py-2 text-sm">Bỏ dòng</button>
                </div>
                <div data-product-options class="space-y-3"></div>
                <p data-line-price class="text-sm font-semibold" aria-live="polite"></p>
            </fieldset>
        </template>

        @foreach ($products as $product)
            <template data-options-for="{{ $product->id }}">
                <div class="grid gap-3 sm:grid-cols-3">
                    <label class="grid gap-1 text-sm">
                        Size
                        <select data-field="size_id" class="w-full rounded-lg border border-terrasse-ink/30 bg-terrasse-paper p-2" required>
                            @foreach ($product->activeSizes as $size)
                                <option value="{{ $size->id }}" data-price="{{ $size->price_vnd }}">{{ $size->label }} · {{ number_format($size->price_vnd, 0, ',', '.') }} ₫</option>
                            @endforeach
                        </select>
                    </label>
                    @foreach (['sugar_level' => ['Đường', $product->allows_sugar], 'ice_level' => ['Đá', $product->allows_ice]] as $field => [$label, $enabled])
                        @if ($enabled)
                            <label class="grid gap-1 text-sm">
                                {{ $label }}
                                <select data-field="{{ $field }}" class="w-full rounded-lg border border-terrasse-ink/30 bg-terrasse-paper p-2" required>
                                    @foreach (DrinkLevel::cases() as $level)
                                        <option value="{{ $level->value }}" @selected($level === DrinkLevel::Standard)>{{ $level->label() }}</option>
                                    @endforeach
                                </select>
                            </label>
                        @endif
                    @endforeach
                </div>
                @if ($product->activeToppings->isNotEmpty())
                    <fieldset class="space-y-1">
                        <legend class="text-sm font-semibold">Topping mỗi ly</legend>
                        @foreach ($product->activeToppings as $topping)
                            <label class="flex items-center gap-2 text-sm">
                                <input type="checkbox" data-field="topping_ids" value="{{ $topping->id }}" data-price="{{ $topping->price_vnd }}">
                                {{ $topping->name }} · +{{ number_format($topping->price_vnd, 0, ',', '.') }} ₫
                            </label>
                        @endforeach
                    </fieldset>
                @endif
            </template>
        @endforeach
    </form>
@endif
