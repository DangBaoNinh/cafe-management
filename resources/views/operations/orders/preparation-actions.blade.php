@use('App\Enums\OrderStatus')
@use('App\Enums\PaymentMethod')
@use('App\Enums\PaymentStatus')

@php($preparing = $order->status === OrderStatus::Preparing)

<section class="space-y-4 rounded-xl border border-coffee/15 bg-white p-4">
    <h2 class="text-lg font-semibold">Pha chế</h2>
    @if ($preparing || $order->payment?->allowsPreparation())
        <form method="POST" action="{{ route('operations.orders.prepare', $order->order_number) }}">
            @csrf
            <input type="hidden" name="expected_status" value="{{ $order->status->value }}">
            <input type="hidden" name="target_status" value="{{ ($preparing ? OrderStatus::Ready : OrderStatus::Preparing)->value }}">
            <flux:button type="submit" variant="primary">{{ $preparing ? 'Đánh dấu sẵn sàng' : 'Bắt đầu pha chế' }}</flux:button>
        </form>
    @elseif ($order->payment?->method === PaymentMethod::BankTransfer && $order->payment->status === PaymentStatus::Pending)
        <p>Đơn chuyển khoản cần được đối chiếu trước khi pha chế.</p>
        <flux:link :href="route('operations.payments.show', $order->order_number)">Đối chiếu chuyển khoản</flux:link>
    @else
        <p>Kiểm tra trạng thái thanh toán trước khi bắt đầu pha chế.</p>
    @endif
</section>
