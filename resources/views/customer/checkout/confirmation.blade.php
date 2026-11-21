<x-layouts::customer title="Đã đặt hàng">
    <section class="mx-auto max-w-2xl space-y-5">
        <h1 class="text-3xl font-semibold tracking-tight">Đã đặt hàng</h1>
        <div class="space-y-2 rounded-xl border border-terrasse-ink/15 bg-terrasse-paper p-4">
            <p class="font-semibold wrap-anywhere">Mã đơn {{ $order->order_number }}</p>
            <p>{{ $order->fulfillment_type->label() }} · {{ $payment->method->label() }}</p>
        </div>

        <x-customer.order-totals :subtotal="$order->subtotal_vnd" :discount="$order->discount_vnd"
            :delivery-fee="$order->delivery_fee_vnd" :total="$order->total_vnd" />

        @if ($payment->status === \App\Enums\PaymentStatus::Pending && $payment->transfer_details !== null && $order->total_vnd === 0)
            <p role="status" class="rounded-xl border border-terrasse-ink/15 bg-terrasse-paper p-4">Tổng tiền bằng 0 ₫. Bạn không cần chuyển khoản.</p>
        @elseif ($payment->status === \App\Enums\PaymentStatus::Pending && $payment->transfer_details !== null)
            <div class="space-y-3 rounded-xl border border-terrasse-ink/15 bg-terrasse-paper p-4">
                <h2 class="font-semibold">Thông tin chuyển khoản</h2>
                <dl class="grid gap-3 text-sm sm:grid-cols-2">
                    @foreach ([
                        'Ngân hàng' => $payment->transfer_details['bank_name'],
                        'Số tài khoản' => $payment->transfer_details['account_number'],
                        'Chủ tài khoản' => $payment->transfer_details['account_holder'],
                        'Nội dung chuyển khoản' => $order->order_number,
                    ] as $label => $value)
                        <div class="space-y-1">
                            <dt>{{ $label }}</dt>
                            <dd class="font-semibold wrap-anywhere">{{ $value }}</dd>
                        </div>
                    @endforeach
                </dl>
                <p class="text-sm">Chuyển đúng tổng tiền ở trên. Cửa hàng sẽ đối chiếu giao dịch trước khi pha chế.</p>
            </div>
        @endif

        <div class="flex flex-wrap items-center gap-4">
            <x-customer.start-demo-payment :order="$order" :payment="$payment" />
            <flux:button variant="primary" :href="route('orders.show', $order->order_number)">Theo dõi đơn</flux:button>
            <flux:link :href="route('menu.index')">Về thực đơn</flux:link>
        </div>
    </section>
</x-layouts::customer>
