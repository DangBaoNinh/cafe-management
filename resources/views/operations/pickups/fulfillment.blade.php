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
                    @if($fromOrder ?? false)<input type="hidden" name="return_to" value="order">@endif
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
                    <flux:button type="button" disabled>Hoàn tất bàn giao và xuất hóa đơn</flux:button>
                @elseif ($order->status !== \App\Enums\OrderStatus::Ready)
                    <p class="text-sm">Đơn chưa sẵn sàng để bàn giao.</p>
                @elseif (! $order->payment?->allowsHandoff())
                    <p class="text-sm">Thanh toán đơn chưa đủ điều kiện bàn giao.</p>
                @elseif ($invoice['waiting'])
                    <p class="text-sm">Còn phụ phí qua ứng dụng cần xử lý trước khi bàn giao.</p>
                @else
                    <form method="POST" action="{{ route('operations.pickups.handoff', $pickup->code) }}" class="space-y-3">
                        @csrf
                        @if($order->payment?->method === \App\Enums\PaymentMethod::Cod)<x-operations.collection-method :amount="$invoice['total']" :memo="$order->order_number" />@endif
                        @if ($invoice['total'] > 0)
                            <flux:input name="collected_vnd" type="number" min="0" step="1" label="Số tiền thực thu tại quầy (₫)" :value="is_scalar(old('collected_vnd')) ? old('collected_vnd') : $invoice['total']" required />
                            <flux:error name="collected_vnd" />
                        @endif
                        <label class="flex items-start gap-2 text-sm"><input class="mt-0.5 size-4 shrink-0 accent-accent" type="checkbox" name="confirmed" value="1" required><span>Khách đã nhận đơn và đã thanh toán đủ tiền.</span></label>
                        <flux:error name="confirmed" />
                        <flux:button type="submit" variant="primary">Hoàn tất bàn giao và xuất hóa đơn</flux:button>
                    </form>
                @endif
            </section>
        @endif
