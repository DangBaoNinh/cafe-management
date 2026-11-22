@use('App\Enums\ReservationStatus')

<x-layouts::admin title="Gọi món tại bàn">
    <section class="space-y-6">
        <header class="flex flex-wrap items-center justify-between gap-2">
            <h1 class="text-2xl font-semibold">Gọi món tại bàn</h1>
            <flux:link :href="route('operations.table-sessions.index')">Cập nhật trạng thái</flux:link>
        </header>
        @include('table-orders.feedback')
        @foreach ($seats as $floor => $floorSeats)
            <section class="space-y-3">
                <h2 class="text-lg font-semibold">Tầng {{ $floor }}</h2>
                <ul class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                    @foreach ($floorSeats as $seat)
                        <li class="space-y-3 rounded-xl border border-terrasse-ink/15 bg-terrasse-paper p-4">
                            <div>
                                <p class="font-semibold">{{ $seat::KINDS[$seat->kind] }} {{ $seat->code }}</p>
                                <p class="text-sm">
                                    {{ $seat->availabilityLabel() }}
                                    @if ($seat->activeTableSession) · {{ $seat->activeTableSession->status->label() }} @endif
                                    @if ($seat->activeTableSession?->checkout_requested_at && $seat->activeTableSession->paid_at === null)
                                        · Yêu cầu tính tiền
                                    @endif
                                </p>
                                @if ($seat->activeTableSession?->pending_rounds_count > 0)
                                    <p class="text-sm font-semibold">{{ $seat->activeTableSession->pending_rounds_count }} lượt chờ tiếp nhận</p>
                                @endif
                            </div>
                            <div class="flex flex-wrap items-center gap-3">
                                @if ($seat->activeTableSession)
                                    <flux:link :href="route('operations.table-sessions.show', $seat->activeTableSession)">Xem phiên</flux:link>
                                @elseif ($seat->is_active && ($seat->activeReservation === null || $seat->activeReservation->status === ReservationStatus::CheckedIn))
                                    <form method="POST" action="{{ route('operations.table-sessions.open', $seat->code) }}">
                                        @csrf
                                        <flux:button type="submit" size="sm">Mở phiên</flux:button>
                                    </form>
                                @elseif ($seat->activeReservation)
                                    <flux:link :href="route('operations.reservations.show', $seat->activeReservation->code)">Xem lịch đặt</flux:link>
                                @endif
                                <flux:link :href="route('operations.table-sessions.qr', $seat->code)">Mã QR</flux:link>
                            </div>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endforeach

        @if ($closedSessions->isNotEmpty())
            <section class="space-y-3">
                <h2 class="text-lg font-semibold">Phiên đã đóng</h2>
                <ul class="divide-y divide-terrasse-ink/15 rounded-lg border border-terrasse-ink/15 bg-terrasse-paper">
                    @foreach ($closedSessions as $closedSession)
                        <li class="flex flex-wrap items-center justify-between gap-2 p-3">
                            <div>
                                <flux:link :href="route('operations.table-sessions.show', $closedSession)">Tầng {{ $closedSession->seat->floor }} · {{ $closedSession->seat->code }}</flux:link>
                                <p class="text-sm">Đã trả chỗ lúc <x-customer.order-time :date="$closedSession->closed_at" /></p>
                            </div>
                            <span>{{ number_format($closedSession->total_vnd, 0, ',', '.') }} ₫</span>
                        </li>
                    @endforeach
                </ul>
                {{ $closedSessions->links() }}
            </section>
        @endif
    </section>
</x-layouts::admin>
