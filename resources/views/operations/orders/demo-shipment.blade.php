@use('App\Enums\OrderStatus')
@use('App\Enums\PaymentMethod')

@if ($shipment !== null || ($order->status === OrderStatus::Ready && $order->payment?->allowsHandoff() && filled($order->address_snapshot['address_line'] ?? null)))
    <section class="space-y-4 rounded-xl border border-coffee/15 bg-white p-4">
        <h2 class="text-lg font-semibold">Vận đơn mô phỏng</h2>
        @if ($shipment === null)
            <p class="text-sm">Tạo vận đơn để trình diễn quy trình giao hàng.</p>
            <form method="POST" action="{{ route('operations.orders.demo-shipment.create', $order->order_number) }}" class="flex flex-wrap gap-3">
                @csrf
                <flux:button type="submit" variant="primary">Tạo vận đơn mô phỏng</flux:button>
                <flux:button type="submit" name="simulate_unavailable" value="1">Mô phỏng mất kết nối</flux:button>
            </form>
        @else
            <dl class="grid gap-3 text-sm sm:grid-cols-2">
                <div class="wrap-anywhere">
                    <dt>Mã vận đơn</dt>
                    <dd class="font-semibold">{{ $shipment->tracking_number }}</dd>
                </div>
                <div>
                    <dt>Mốc vận chuyển</dt>
                    <dd class="font-semibold">
                        @if ($shipment->delivered_at !== null)
                            Đã giao khách · <x-customer.order-time :date="$shipment->delivered_at" />
                        @elseif ($shipment->picked_up_at !== null)
                            Đã nhận từ quán · <x-customer.order-time :date="$shipment->picked_up_at" />
                        @elseif ($order->status === OrderStatus::Ready)
                            Chờ đối tác nhận đơn
                        @else
                            Đơn đã dừng
                        @endif
                    </dd>
                </div>
            </dl>

            @if (in_array($order->status, [OrderStatus::Ready, OrderStatus::OutForDelivery], true))
                @php($delivering = $order->status === OrderStatus::OutForDelivery)
                <form method="POST" action="{{ route('operations.orders.demo-shipment.simulate', $order->order_number) }}" class="space-y-4">
                    @csrf
                    <input type="hidden" name="event" value="{{ $delivering ? 'delivered' : 'picked_up' }}">
                    @if ($delivering && $order->payment?->method === PaymentMethod::Cod)
                        <div class="space-y-2">
                            <label class="block text-sm font-medium" for="demo-cod-amount">Số tiền COD đã thu (VND)</label>
                            <flux:input id="demo-cod-amount" name="cod_amount_vnd" inputmode="numeric" pattern="[0-9]+" :value="is_scalar(old('cod_amount_vnd')) ? old('cod_amount_vnd') : ''" required />
                            <flux:error name="cod_amount_vnd" />
                        </div>
                    @endif
                    <flux:button type="submit" variant="primary">{{ $delivering ? 'Mô phỏng giao thành công' : 'Mô phỏng đối tác nhận đơn' }}</flux:button>
                </form>
            @endif
        @endif
    </section>
@endif
