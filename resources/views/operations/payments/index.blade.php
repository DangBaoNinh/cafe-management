<x-layouts::admin title="Đối chiếu thanh toán">
    <section class="mx-auto max-w-3xl space-y-6">
        <h1 class="text-2xl font-semibold sm:text-3xl">Đối chiếu thanh toán</h1>

        @if ($payments->isEmpty())
            <p class="rounded-xl border border-coffee/15 bg-white p-5">
                {{ $payments->total() > 0 ? 'Trang này không có khoản thanh toán.' : 'Chưa có chuyển khoản cần đối chiếu hoặc khoản chờ hoàn tiền.' }}
            </p>
        @else
            <ul class="divide-y divide-coffee/15 rounded-xl border border-coffee/15 bg-white">
                @foreach ($payments as $payment)
                    <li class="space-y-3 p-4 sm:p-5">
                        <div class="flex flex-wrap items-baseline justify-between gap-2">
                            <h2 class="min-w-0 font-semibold wrap-anywhere">
                                <flux:link :href="route('operations.payments.show', $payment->order->order_number)">{{ $payment->order->order_number }}</flux:link>
                            </h2>
                            <strong><x-customer.vnd :amount="$payment->amount_vnd" /></strong>
                        </div>
                        <dl class="grid gap-x-4 gap-y-2 text-sm sm:grid-cols-3">
                            <div>
                                <dt class="font-medium">Giờ đặt</dt>
                                <dd><x-customer.order-time :date="$payment->order->created_at" /></dd>
                            </div>
                            <div>
                                <dt class="font-medium">Trạng thái đơn</dt>
                                <dd>{{ $payment->order->status->label() }}</dd>
                            </div>
                            <div>
                                <dt class="font-medium">{{ $payment->method->label() }}</dt>
                                <dd>{{ $payment->status->label() }}</dd>
                            </div>
                        </dl>
                    </li>
                @endforeach
            </ul>
        @endif

        {{ $payments->links() }}
    </section>
</x-layouts::admin>
