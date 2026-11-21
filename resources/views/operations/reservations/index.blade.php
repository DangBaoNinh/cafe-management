<x-layouts::admin title="Lịch đặt bàn và phòng">
    <section class="mx-auto max-w-5xl space-y-5">
        <h1 class="text-2xl font-semibold">Lịch đặt bàn và phòng</h1>
        <flux:link :href="route('operations.reservations.seats')">Sơ đồ và khách đến trực tiếp</flux:link>
        @include('reservations.feedback')
        <form method="POST" action="{{ route('operations.reservations.lookup') }}" class="flex flex-wrap items-end gap-3 rounded-xl border border-terrasse-ink/15 bg-terrasse-paper p-4">
            @csrf
            <div class="min-w-0 flex-1">
                <flux:input name="code" label="Mã đặt chỗ" maxlength="16" :value="is_string(old('code')) ? old('code') : null" required />
            </div>
            <flux:button type="submit" variant="primary">Tra mã</flux:button>
        </form>
        <form method="GET" action="{{ route('operations.reservations.index') }}" class="grid items-end gap-3 sm:grid-cols-2 lg:grid-cols-5">
            <flux:input type="date" name="date" label="Ngày đến" :value="$date" />
            <flux:select name="floor" label="Tầng">
                <flux:select.option value="">Cả hai tầng</flux:select.option>
                @foreach ([1, 2] as $floor)
                    <flux:select.option :value="$floor" :selected="(int) ($filters['floor'] ?? 0) === $floor">Tầng {{ $floor }}</flux:select.option>
                @endforeach
            </flux:select>
            <flux:select name="kind" label="Loại chỗ">
                <flux:select.option value="">Tất cả</flux:select.option>
                @foreach (\App\Models\ReservationSeat::KINDS as $value => $label)
                    <flux:select.option :value="$value" :selected="($filters['kind'] ?? null) === $value">{{ $label }}</flux:select.option>
                @endforeach
            </flux:select>
            <flux:select name="status" label="Trạng thái">
                <flux:select.option value="">Tất cả</flux:select.option>
                @foreach (\App\Enums\ReservationStatus::cases() as $status)
                    <flux:select.option :value="$status->value" :selected="($filters['status'] ?? null) === $status->value">{{ $status->label() }}</flux:select.option>
                @endforeach
            </flux:select>
            <flux:button type="submit">Lọc lịch</flux:button>
        </form>
        <ul class="divide-y divide-terrasse-ink/15 rounded-xl border border-terrasse-ink/15 bg-terrasse-paper">
            @forelse ($reservations as $reservation)
                <li class="grid gap-2 p-4 sm:grid-cols-4">
                    <flux:link :href="route('operations.reservations.show', $reservation->code)">Tầng {{ $reservation->seat->floor }} · {{ $reservation->seat->code }}</flux:link>
                    <x-customer.order-time :date="$reservation->scheduled_at" />
                    <span>{{ $reservation->party_size }} khách · {{ $reservation->contact_name }}</span>
                    <span>{{ $reservation->status->label() }}</span>
                </li>
            @empty
                <li class="p-4">Không có lượt đặt chỗ phù hợp.</li>
            @endforelse
        </ul>
        {{ $reservations->links() }}
    </section>
</x-layouts::admin>
