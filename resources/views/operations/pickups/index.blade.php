<x-layouts::admin title="Lịch nhận tại quán">
    <section class="mx-auto max-w-4xl space-y-5">
        <h1 class="text-2xl font-semibold">Lịch nhận tại quán</h1>
        <form method="POST" action="{{ route('operations.pickups.lookup') }}" class="space-y-3 rounded-xl border border-terrasse-ink/15 bg-terrasse-paper p-4">
            @csrf
            <flux:input name="code" label="Mã tra cứu trên phiếu" maxlength="16" :value="is_string(old('code')) ? old('code') : null" required />
            <flux:button type="submit" variant="primary">Tra mã phiếu</flux:button>
        </form>
        <form method="GET" action="{{ route('operations.pickups.index') }}" class="grid items-end gap-3 sm:grid-cols-3">
            <flux:input type="date" name="date" label="Ngày nhận" :value="$date" required />
            <flux:select name="time" label="Khung giờ (giờ Việt Nam)">
                <flux:select.option value="">Cả ngày</flux:select.option>
                @foreach (\App\Services\BookingRules::times() as $slot)
                    <flux:select.option :value="$slot" :selected="$time === $slot">{{ $slot }}</flux:select.option>
                @endforeach
            </flux:select>
            <flux:button type="submit">Lọc lịch</flux:button>
        </form>
        <ul class="divide-y divide-terrasse-ink/15 rounded-xl border border-terrasse-ink/15 bg-terrasse-paper">
            @forelse ($pickups as $pickup)
                <li class="grid gap-1 p-4 sm:grid-cols-3">
                    <span><x-customer.order-time :date="$pickup->scheduled_at" /></span>
                    <flux:link class="wrap-anywhere" :href="route('operations.orders.show', $pickup->order->order_number)">{{ $pickup->order->order_number }}</flux:link>
                    <span>{{ $pickup->label($pickup->order) }}</span>
                </li>
            @empty
                <li class="p-4">Không có đơn hẹn trong khung giờ này.</li>
            @endforelse
        </ul>
        {{ $pickups->links() }}
    </section>
</x-layouts::admin>
