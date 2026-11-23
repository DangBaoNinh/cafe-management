<x-layouts::customer title="Phí giao hàng">
    <section class="mx-auto max-w-2xl space-y-5">
        <flux:link :href="route('cart.index')">Về giỏ hàng</flux:link>
        <h1 class="text-3xl font-semibold tracking-tight">Phí giao hàng</h1>

        <div class="space-y-4 rounded-xl border border-terrasse-ink/15 bg-terrasse-paper p-5">
            <h2 class="font-semibold">Giao tận nơi</h2>
            @if ($addresses->isEmpty())
                <p class="text-sm">Thêm địa chỉ để kiểm tra vùng giao và phí.</p>
                <flux:link :href="route('addresses.index')">Thêm địa chỉ giao hàng</flux:link>
            @else
                <form method="GET" action="{{ route('cart.delivery') }}" class="space-y-3">
                    <flux:select name="address" label="Địa chỉ nhận hàng" required>
                        <flux:select.option value="" :selected="$address === null">Chọn địa chỉ</flux:select.option>
                        @foreach ($addresses as $saved)
                            <flux:select.option :value="$saved->id" :selected="$address?->id === $saved->id">
                                {{ $saved->address_line }}, {{ $catalog->districts($saved->province_code)[$saved->district_code] ?? $saved->district_code }}
                            </flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:button type="submit">Kiểm tra phí</flux:button>
                </form>

                @if ($address !== null)
                    <p role="status" class="font-medium">
                        {{ $deliveryFee === null ? 'Không hỗ trợ giao đến địa chỉ này. Bạn có thể nhận tại quán.' : number_format($deliveryFee, 0, ',', '.').' ₫' }}
                    </p>
                @endif
            @endif
        </div>

        <div class="flex flex-wrap justify-between gap-2 rounded-xl border border-terrasse-ink/15 bg-terrasse-paper p-5">
            <h2 class="font-semibold">Nhận tại quán</h2>
            <strong>{{ number_format($pickupFee, 0, ',', '.') }} ₫</strong>
        </div>
    </section>
</x-layouts::customer>
