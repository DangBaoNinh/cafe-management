<x-layouts::customer title="Phiếu đặt chỗ">
    <section class="mx-auto max-w-3xl space-y-5">
        <flux:link :href="route('reservations.index')">Về lịch sử đặt bàn</flux:link>
        <p class="text-sm">Chỉ hủy trong 30 phút từ lúc đặt. Cần <a href="{{ route('profile.edit') }}" class="underline">liên kết ngân hàng trong Hồ sơ</a> để yêu cầu hoàn tiền.</p>
        <h1 class="terrasse-display text-3xl font-semibold">Phiếu đặt chỗ</h1>
        @include('reservations.feedback')
        @include('reservations.summary')
        @include('reservations.deposit')
        @if($reservation->status === \App\Enums\ReservationStatus::PendingDeposit)<p>Yêu cầu này chưa được xác nhận. Bạn có thể quay lại phiếu để chuyển cọc sau.</p><flux:button :href="route('spaces')">Quay lại không gian — chưa xác nhận</flux:button>@endif
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
                            <x-payment-qr :amount="$fee->amount_vnd" :memo="$fee->transfer_code" :bank="$fee->bank_details" />
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
        @endif
        @if($reservation->status === \App\Enums\ReservationStatus::Confirmed)
            <details class="rounded-xl border border-terrasse-ink/15 bg-terrasse-paper p-4" @if ($errors->has('confirm')) open @endif>
                <summary class="cursor-pointer font-semibold">Hủy lịch</summary>
                <form method="POST" action="{{ route('reservations.cancel', $reservation->code) }}" class="mt-4 space-y-3">
                    @csrf
                    <label class="flex items-center gap-2">
                        <input type="checkbox" name="confirm" value="1" required>
                        Tôi xác nhận hủy lịch và trả chỗ
                    </label>
                    <p>Hủy trong 30 phút được hoàn tiền demo về ngân hàng liên kết. Quá hạn sẽ không hủy được.</p><flux:button type="submit" variant="danger" :disabled="!$reservation->canCustomerCancel()" data-cancel-until="{{ $reservation->created_at->copy()->addMinutes(30)->getTimestamp() * 1000 }}">Xác nhận hủy</flux:button>
                </form>
            </details>
        @endif
        <section class="space-y-3 rounded-xl border border-terrasse-ink/15 bg-terrasse-paper p-4">
            <h2 class="text-lg font-semibold">Lịch sử xử lý đặt bàn</h2>
            <ol class="space-y-3">
                @forelse($reservation->events as $event)
                    <li class="space-y-1 border-l-2 border-terrasse-ink/20 pl-3 text-sm">
                        <p class="font-semibold">{{ $event->from_status === $event->to_status ? 'Điều chỉnh lịch đặt' : (\App\Enums\ReservationStatus::tryFrom($event->to_status)?->label() ?? 'Cập nhật đặt bàn') }}</p>
                        <p><x-customer.order-time :date="$event->occurred_at" /></p>
                        @if($event->reason)<p class="break-words">{{ $event->reason }}</p>@endif
                    </li>
                @empty
                    <li class="text-sm">Lịch đặt này chưa có bản ghi xử lý.</li>
                @endforelse
            </ol>
        </section>
    </section>
</x-layouts::customer>
