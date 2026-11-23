<x-layouts::admin title="Đối chiếu đặt chỗ">
    <section class="mx-auto max-w-3xl space-y-5">
        <flux:link :href="route('operations.reservations.index')">Về lịch đặt bàn và phòng</flux:link>
        <h1 class="text-2xl font-semibold">Đối chiếu đặt chỗ</h1>
        @include('reservations.feedback')
        @include('reservations.summary')
        @can('access-admin') @include('reservations.deposit') @endcan
        @if ($reservation->status === \App\Enums\ReservationStatus::Confirmed)
            <section class="space-y-3 rounded-xl border border-terrasse-ink/15 bg-terrasse-paper p-4">
                @can('access-admin')<details class="space-y-3"><summary class="cursor-pointer font-bold">Điều chỉnh ngày / giờ đặt bàn</summary>
                    <form method="POST" action="{{ route('operations.reservations.reschedule', $reservation->code) }}" class="space-y-3">@csrf
                        <input type="hidden" name="revision" value="{{ $reservation->revision }}">
                        <flux:input type="date" name="pickup_date" label="Ngày đến" :value="$reservation->scheduled_at->setTimezone('Asia/Ho_Chi_Minh')->format('Y-m-d')" required />
                        <flux:input type="time" name="pickup_clock" label="Giờ đến" :value="$reservation->scheduled_at->setTimezone('Asia/Ho_Chi_Minh')->format('H:i')" required step="300" />
                        <label><input type="checkbox" name="confirm" value="1" required> Xác nhận thay đổi lịch, giữ nguyên tiền cọc.</label>
                        <flux:button type="submit">Cập nhật lịch đặt</flux:button>
                    </form>
                </details>@endcan
                <h2 class="text-lg font-semibold">Nhận khách / mở quyền thao tác bàn</h2><p>Chỉ nhận khách đúng ngày đặt. Xác nhận tại đây mới áp dụng cọc cho order.</p>
                @if (now()->gte($reservation->arrival_deadline))
                    <p>Đã quá hạn check-in. Hệ thống sẽ ghi nhận không đến.</p>
                @else
                    <form method="POST" action="{{ route('operations.reservations.check-in', $reservation->code) }}">
                        @csrf
                        <flux:button type="submit" variant="primary" :disabled="$reservation->scheduled_at->setTimezone('Asia/Ho_Chi_Minh')->toDateString() !== now('Asia/Ho_Chi_Minh')->toDateString()">Ghi nhận khách đến</flux:button>
                    </form>
                @endif
                @if (auth()->user()->can('access-admin') && now()->lt($reservation->arrival_deadline))
                    <details class="border-t border-terrasse-ink/15 pt-3">
                        <summary class="cursor-pointer font-semibold">Hủy lịch</summary>
                        <form method="POST" action="{{ route('operations.reservations.cancel', $reservation->code) }}" class="mt-3 space-y-3">
                            @csrf
                            <flux:textarea name="reason" label="Lý do hủy" maxlength="500" rows="2" required />
                            <flux:button type="submit" variant="danger">Hủy lịch và trả chỗ</flux:button>
                        </form>
                    </details>
                @endif
            </section>
        @elseif ($reservation->status === \App\Enums\ReservationStatus::CheckedIn)
            @if ($reservation->tableSession)
                <flux:link :href="route('operations.table-sessions.show', $reservation->tableSession)">Xem phiên gọi món và trả chỗ</flux:link>
            @else
                <div class="flex flex-wrap gap-2 rounded-xl border border-terrasse-ink/15 bg-terrasse-paper p-4">
                    <form method="POST" action="{{ route('operations.table-sessions.open', $reservation->seat->code) }}">
                        @csrf
                        <flux:button type="submit" variant="primary">Mở phiên gọi món</flux:button>
                    </form>
                    <form method="POST" action="{{ route('operations.reservations.release', $reservation->code) }}">
                        @csrf
                        <flux:button type="submit">Xác nhận khách rời đi và trả chỗ</flux:button>
                    </form>
                </div>
            @endif
        @endif
        <section class="space-y-4 rounded-xl border border-terrasse-ink/15 bg-terrasse-paper p-4">
            <h2 class="text-lg font-semibold">Phụ phí và hóa đơn tại quầy</h2>
            @forelse ($reservation->fees as $fee)
                @include('operations.bookings.fee', [
                    'canSettle' => $reservation->canSettleFees() && $reservation->tableSession?->billed_at === null,
                    'feeRoutes' => 'operations.reservations',
                    'bookingCode' => $reservation->code,
                ])
            @empty
                <p>Chưa phát sinh phụ phí.</p>
            @endforelse
            <p class="font-semibold">Còn phải thu tại quầy · {{ number_format($counterTotal, 0, ',', '.') }} ₫</p>
            @if ($counterTotal > 0 && $reservation->tableSession)
                <flux:link :href="route('operations.table-sessions.show', $reservation->tableSession)">Thu phụ phí trên hóa đơn tại bàn</flux:link>
            @elseif ($counterTotal > 0 && $reservation->arrived_at !== null && $reservation->canSettleFees())
                <form method="POST" action="{{ route('operations.reservations.collect', $reservation->code) }}" class="space-y-3">
                    @csrf
                    <flux:input name="amount_vnd" type="number" min="1" step="1" label="Số tiền đã thu (₫)" required />
                    <flux:button type="submit">Xác nhận thu tại quầy</flux:button>
                </form>
            @endif
        </section>
        <section class="space-y-3 rounded-xl border border-terrasse-ink/15 bg-terrasse-paper p-4">
            <h2 class="text-lg font-semibold">Lịch sử</h2>
            <ul class="space-y-2 text-sm">
                @foreach ($reservation->events->sortBy('id') as $event)
                    <li>{{ \App\Enums\ReservationStatus::from($event->to_status)->label() }} ·
                        <x-customer.order-time :date="$event->occurred_at" />
                        @if ($event->reason !== null) · {{ $event->reason }} @endif
                    </li>
                @endforeach
            </ul>
        </section>
    </section>
</x-layouts::admin>
