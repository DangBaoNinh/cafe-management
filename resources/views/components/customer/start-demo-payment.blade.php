@props(['order', 'payment'])

@if (\App\Services\DemoPaymentGateway::enabled() && \App\Services\DemoPaymentGateway::canPay($order, $payment))
    <form method="POST" action="{{ route('orders.demo-payment.start', $order->order_number) }}">
        @csrf
        <flux:button type="submit">Mở cổng thanh toán mô phỏng</flux:button>
    </form>
@endif
