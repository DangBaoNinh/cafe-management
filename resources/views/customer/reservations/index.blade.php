<x-layouts::customer title="Lịch đặt chỗ của tôi">
    <section class="mx-auto max-w-3xl space-y-5">
        <h1 class="terrasse-display text-3xl font-semibold">Lịch đặt chỗ của tôi</h1>
        <flux:button variant="primary" :href="route('reservations.create')">Đặt chỗ tại quán</flux:button>
        <ul class="divide-y divide-terrasse-ink/15 rounded-xl border border-terrasse-ink/15 bg-terrasse-paper">
            @forelse ($reservations as $reservation)
                <li class="grid gap-1 p-4 sm:grid-cols-3">
                    <flux:link :href="route('reservations.show', $reservation->code)">Tầng {{ $reservation->seat->floor }} · {{ $reservation->seat->code }}</flux:link>
                    <x-customer.order-time :date="$reservation->scheduled_at" />
                    <span>{{ $reservation->status->label() }}</span>
                </li>
            @empty
                <li class="p-4">Bạn chưa đặt chỗ tại quán.</li>
            @endforelse
        </ul>
        {{ $reservations->links() }}
    </section>
</x-layouts::customer>
