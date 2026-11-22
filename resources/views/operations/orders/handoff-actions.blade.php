@use('App\Enums\OrderStatus')
@use('App\Enums\PaymentMethod')

@php
    $dispatching = $target === OrderStatus::OutForDelivery;
    $payment = $order->payment;
@endphp

<section class="space-y-4 rounded-xl border border-terrasse-ink/15 bg-terrasse-paper p-4">
    <h2 class="text-lg font-semibold">Bàn giao</h2>
    @if ($order->status === OrderStatus::OutForDelivery)
        <p class="text-sm">Nếu chưa giao được, giữ đơn ở trạng thái Đang giao để cửa hàng xử lý.</p>
    @endif
    @if ($payment?->allowsHandoff())
        <form method="POST" action="{{ route('operations.orders.handoff', $order->order_number) }}" class="space-y-4">
            @csrf
            <input type="hidden" name="expected_status" value="{{ $order->status->value }}">
            <input type="hidden" name="target_status" value="{{ $target->value }}">
            <input type="hidden" name="fulfillment_type" value="{{ $order->fulfillment_type->value }}">
            <input type="hidden" name="method" value="{{ $payment->method->value }}">

            @if (! $dispatching && $payment->method === PaymentMethod::Cod)
                <x-operations.collection-method />
                <div class="space-y-2">
                    <label class="block text-sm font-medium" for="cod-amount">Số tiền COD đã thu (VND)</label>
                    <flux:input id="cod-amount" name="cod_amount_vnd" inputmode="numeric" pattern="[0-9]+" :value="is_scalar(old('cod_amount_vnd')) ? old('cod_amount_vnd') : $order->total_vnd" required />
                    <flux:error name="cod_amount_vnd" />
                </div>
            @endif

            <label class="flex items-start gap-2 text-sm font-medium">
                <input class="mt-0.5 size-4 shrink-0 accent-accent" type="checkbox" name="confirmed" value="1" required>
                <span>{{ $dispatching ? 'Đã bàn giao đơn cho người giao hàng.' : 'Khách đã nhận đơn và đã thanh toán đủ tiền.' }}</span>
            </label>
            <flux:error name="confirmed" />
            <flux:button type="submit" variant="primary">{{ $dispatching ? 'Đánh dấu đang giao' : 'Hoàn tất bàn giao và xuất hóa đơn' }}</flux:button>
        </form>
    @else
        <p class="text-sm">Kiểm tra trạng thái thanh toán trước khi bàn giao đơn.</p>
    @endif
</section>
