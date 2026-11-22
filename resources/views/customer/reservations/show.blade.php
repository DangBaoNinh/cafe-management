<x-layouts::customer title="Phiếu đặt chỗ">
    <section class="mx-auto max-w-3xl space-y-5">
        <flux:link :href="route('reservations.index')">Lịch đặt của tôi</flux:link>
        <h1 class="terrasse-display text-3xl font-semibold">Phiếu đặt chỗ</h1>
        @include('reservations.feedback')
        @include('reservations.summary')
        @if ($reservation->status === \App\Enums\ReservationStatus::Confirmed && now()->lt($reservation->arrival_deadline))
            <p>Trình mã cho nhân viên từ giờ hẹn đến trước <x-customer.order-time :date="$reservation->arrival_deadline" />.</p>
        @endif
        @if ($reservation->fees->isNotEmpty())
            <section class="space-y-3 rounded-xl border border-terrasse-ink/15 bg-terrasse-paper p-4">
                <h2 class="font-semibold">Phụ phí</h2>
                @foreach ($reservation->fees as $fee)
                    <div class="space-y-1 border-t border-terrasse-ink/15 pt-2 text-sm">
                        <p>{{ $fee->label() }} · {{ number_format($fee->amount_vnd, 0, ',', '.') }} ₫</p>
                        <p>{{ \App\Models\PickupFee::CHANNELS[$fee->channel] }} · {{ $fee->statusLabel() }}</p>
                        @if ($reservation->canSettleFees() && $fee->channel === 'app' && $fee->status === 'pending' && $fee->bank_details !== null)
                            <p>{{ $fee->bank_details['bank_name'] }} · {{ $fee->bank_details['account_number'] }} · {{ $fee->bank_details['account_holder'] }}</p>
                            <p>Nội dung chuyển khoản <strong class="font-mono">{{ $fee->transfer_code }}</strong></p>
                        @endif
                    </div>
                @endforeach
            </section>
        @endif
        @if ($reservation->canChange())
            <details class="rounded-xl border border-terrasse-ink/15 bg-terrasse-paper p-4" @if ($errors->hasAny(['pickup_time', 'pickup_date', 'pickup_clock', 'revision'])) open @endif>
                <summary class="cursor-pointer font-semibold">Đổi giờ</summary>
                <form method="POST" action="{{ route('reservations.reschedule', $reservation->code) }}" class="mt-4 space-y-3">
                    @csrf
                    <input type="hidden" name="revision" value="{{ $reservation->revision }}">
                    <p class="text-sm">Phí đổi giờ 10.000 ₫ qua kênh {{ mb_strtolower(\App\Models\PickupFee::CHANNELS[$reservation->fee_channel]) }}. Giờ mới chênh tối đa 60 phút.</p>
                    <x-customer.pickup-slot :date="$reservation->scheduled_at->setTimezone(\App\Services\BookingRules::TIMEZONE)->toDateString()"
                        :$slots day-label="Ngày đến" time-label="Giờ đến (giờ Việt Nam)" />
                    <flux:button type="submit" variant="primary">Xác nhận đổi giờ</flux:button>
                </form>
            </details>
            <details class="rounded-xl border border-terrasse-ink/15 bg-terrasse-paper p-4" @if ($errors->has('confirm')) open @endif>
                <summary class="cursor-pointer font-semibold">Hủy lịch</summary>
                <form method="POST" action="{{ route('reservations.cancel', $reservation->code) }}" class="mt-4 space-y-3">
                    @csrf
                    <label class="flex items-center gap-2">
                        <input type="checkbox" name="confirm" value="1" required>
                        Tôi xác nhận hủy lịch và trả chỗ
                    </label>
                    <flux:button type="submit" variant="danger">Xác nhận hủy</flux:button>
                </form>
            </details>
        @endif
    </section>
</x-layouts::customer>
