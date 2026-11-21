<x-layouts::admin title="Đối chiếu phiếu nhận">
    <section class="mx-auto max-w-3xl space-y-5">
        <flux:link :href="route('operations.pickups.index')">Về lịch nhận tại quán</flux:link>
        <h1 class="text-2xl font-semibold">Đối chiếu phiếu nhận</h1>
        @if (session('status'))
            <p role="status" class="rounded-lg border border-terrasse-ink/15 bg-terrasse-paper p-4">{{ session('status') }}</p>
        @endif
        @foreach ($errors->all() as $message)
            <p role="alert" class="text-sm text-terrasse-danger">{{ $message }}</p>
        @endforeach
        <div class="space-y-2 rounded-xl border border-terrasse-ink/15 bg-terrasse-paper p-4">
            <p class="font-mono font-bold">{{ $pickup->code }}</p>
            <flux:link class="wrap-anywhere" :href="route('operations.orders.show', $order->order_number)">Đơn {{ $order->order_number }}</flux:link>
            <p>Giờ hẹn · <x-customer.order-time :date="$pickup->scheduled_at" /></p>
            <p>{{ $pickup->label($order) }} · {{ $order->payment?->status->label() }}</p>
            @if ($pickup->arrived_at !== null)
                <p>Khách đến lúc <x-customer.order-time :date="$pickup->arrived_at" /></p>
            @elseif ($pickup->isOpen($order))
                <form method="POST" action="{{ route('operations.pickups.arrive', $pickup->code) }}" class="space-y-3">
                    @csrf
                    <x-customer.pickup-fee-channel :$bankAvailable label="Kênh thu nếu có phí đến trễ" />
                    <flux:button type="submit" variant="primary">Ghi nhận khách đến</flux:button>
                </form>
            @endif
        </div>
        <section class="space-y-4 rounded-xl border border-terrasse-ink/15 bg-terrasse-paper p-4">
            <h2 class="text-lg font-semibold">Phụ phí</h2>
            @forelse ($pickup->fees as $fee)
                @include('operations.bookings.fee', ['canSettle' => $pickup->isOpen($order), 'feeRoutes' => 'operations.pickups', 'bookingCode' => $pickup->code])
            @empty
                <p class="text-sm">Chưa phát sinh phụ phí.</p>
            @endforelse
        </section>
        @if ($pickup->isOpen($order))
            <section class="space-y-3 rounded-xl border border-terrasse-ink/15 bg-terrasse-paper p-4">
                <h2 class="text-lg font-semibold">Hóa đơn tại quầy</h2>
                <dl class="space-y-1 text-sm">
                    <div class="flex justify-between gap-3"><dt>Tiền đơn thu tại quầy</dt><dd>{{ number_format($invoice['items'], 0, ',', '.') }} ₫</dd></div>
                    <div class="flex justify-between gap-3"><dt>Phụ phí tại quầy</dt><dd>{{ number_format($invoice['fees'], 0, ',', '.') }} ₫</dd></div>
                    <div class="flex justify-between gap-3 font-semibold"><dt>Tổng phải thu</dt><dd>{{ number_format($invoice['total'], 0, ',', '.') }} ₫</dd></div>
                </dl>
                @if ($pickup->arrived_at === null)
                    <p class="text-sm">Ghi nhận khách đến trước khi bàn giao.</p>
                @elseif ($order->status !== \App\Enums\OrderStatus::Ready)
                    <p class="text-sm">Đơn chưa sẵn sàng để bàn giao.</p>
                @elseif (! $order->payment?->allowsHandoff())
                    <p class="text-sm">Thanh toán đơn chưa đủ điều kiện bàn giao.</p>
                @elseif ($invoice['waiting'])
                    <p class="text-sm">Còn phụ phí qua ứng dụng cần xử lý trước khi bàn giao.</p>
                @else
                    <form method="POST" action="{{ route('operations.pickups.handoff', $pickup->code) }}" class="space-y-3">
                        @csrf
                        @if ($invoice['total'] > 0)
                            <flux:input name="collected_vnd" type="number" min="0" step="1" label="Số tiền thực thu tại quầy (₫)" required />
                        @endif
                        <flux:button type="submit" variant="primary">Xác nhận bàn giao</flux:button>
                    </form>
                @endif
            </section>
        @endif
    </section>
</x-layouts::admin>
