<section class="space-y-4 rounded-xl border border-terrasse-ink/15 bg-terrasse-paper p-4 sm:p-6">
    <h2 class="text-lg font-semibold">Phiếu nhận tại quán</h2>
    <dl class="grid gap-4 sm:grid-cols-2">
        <div><dt class="text-sm">Mã tra cứu</dt><dd class="font-mono font-bold">{{ $pickup->code }}</dd></div>
        <div><dt class="text-sm">Mã đơn</dt><dd class="wrap-anywhere">{{ $order->order_number }}</dd></div>
        <div><dt class="text-sm">Giờ hẹn</dt><dd><x-customer.order-time :date="$pickup->scheduled_at" /></dd></div>
        <div><dt class="text-sm">Trạng thái</dt><dd>{{ $pickup->label($order) }}</dd></div>
    </dl>
    @if ($pickup->isOpen($order))
        <p class="text-sm">Trình mã này cho nhân viên khi đến quán.</p>
    @endif
    @foreach ($pickup->fees as $fee)
        <div class="space-y-1 border-t border-terrasse-ink/15 pt-3 text-sm">
            <p class="font-semibold">{{ $fee->label() }} · {{ number_format($fee->amount_vnd, 0, ',', '.') }} ₫</p>
            <p>{{ \App\Models\PickupFee::CHANNELS[$fee->channel] }} · {{ $fee->statusLabel() }}</p>
            @if ($pickup->isOpen($order) && $fee->channel === 'app' && $fee->status === 'pending' && $fee->bank_details !== null)
                <p>{{ $fee->bank_details['bank_name'] }} · {{ $fee->bank_details['account_number'] }} · {{ $fee->bank_details['account_holder'] }}</p>
                <p>Nội dung chuyển khoản <strong class="font-mono">{{ $fee->transfer_code }}</strong></p>
            @endif
        </div>
    @endforeach
    @foreach (['pickup_time', 'pickup_date', 'pickup_clock', 'revision', 'channel'] as $field)
        @error($field)
            <p role="alert" class="text-sm text-terrasse-danger">{{ $message }}</p>
        @enderror
    @endforeach
    @if ($pickup->isOpen($order) && $pickup->arrived_at === null && now()->lt($pickup->scheduled_at))
        <details class="border-t border-terrasse-ink/15 pt-3" @if($errors->hasAny(['pickup_time', 'pickup_date', 'pickup_clock', 'revision', 'channel'])) open @endif>
            <summary class="cursor-pointer font-semibold">Đổi giờ nhận</summary>
            <form method="POST" action="{{ route('orders.reschedule-pickup', $order->order_number) }}" class="mt-3 space-y-3">
                @csrf
                <input type="hidden" name="revision" value="{{ $pickup->revision }}">
                <p class="text-sm">Phí đổi lịch {{ number_format(\App\Services\BookingRules::CHANGE_FEE, 0, ',', '.') }} ₫. Giờ mới chênh tối đa 60 phút.</p>
                <x-customer.pickup-slot :date="$pickup->scheduled_at->setTimezone(\App\Services\BookingRules::TIMEZONE)->toDateString()" />
                <x-customer.pickup-fee-channel :$bankAvailable />
                <flux:button type="submit" variant="primary">Xác nhận đổi giờ</flux:button>
            </form>
        </details>
    @endif
</section>
