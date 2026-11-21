<x-layouts::admin title="Sơ đồ chỗ tại quán">
    <section class="mx-auto max-w-5xl space-y-5">
        <flux:link :href="route('operations.reservations.index')">Về lịch đặt bàn và phòng</flux:link>
        <h1 class="text-2xl font-semibold">Sơ đồ chỗ tại quán</h1>
        <p class="text-sm">Giờ phục vụ 07:00–23:00 · Nhận đặt chỗ {{ $hours->first_slot }}–{{ $hours->last_slot }}</p>
        @include('reservations.feedback')
        @can('access-admin')
            <details class="space-y-3 rounded-xl border border-terrasse-ink/15 bg-terrasse-paper p-4">
                <summary class="cursor-pointer font-semibold">Cấu hình giờ nhận đặt chỗ</summary>
                <form method="POST" action="{{ route('operations.reservations.hours') }}" class="grid items-end gap-3 sm:grid-cols-3">
                    @csrf
                    <flux:select name="first_slot" label="Giờ hẹn đầu tiên" required>
                        @foreach (\App\Services\BookingRules::times() as $slot)
                            <flux:select.option :value="$slot" :selected="$slot === $hours->first_slot">{{ $slot }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:select name="last_slot" label="Giờ hẹn cuối cùng" required>
                        @foreach (\App\Services\BookingRules::times() as $slot)
                            <flux:select.option :value="$slot" :selected="$slot === $hours->last_slot">{{ $slot }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:button type="submit">Lưu giờ nhận đặt chỗ</flux:button>
                </form>
            </details>
        @endcan
        @foreach ($seats as $floor => $floorSeats)
            <x-reservations.floor :$floor :seats="$floorSeats" operational />
        @endforeach
    </section>
</x-layouts::admin>
