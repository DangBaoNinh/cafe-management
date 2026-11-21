<x-layouts::admin :title="$title">
    <section class="mx-auto max-w-3xl space-y-6">
        <h1 class="text-2xl font-semibold sm:text-3xl">{{ $title }}</h1>

        @if ($orders->isEmpty())
            <p class="rounded-xl border border-coffee/15 bg-white p-5">
                {{ $orders->total() > 0 ? 'Trang này không có đơn hàng.' : $emptyMessage }}
            </p>
        @else
            <ul class="divide-y divide-coffee/15 rounded-xl border border-coffee/15 bg-white">
                @foreach ($orders as $order)
                    <li class="space-y-3 p-4 sm:p-5">
                        <div class="flex flex-wrap items-baseline justify-between gap-2">
                            <h2 class="min-w-0 font-semibold wrap-anywhere">
                                <flux:link :href="route('operations.orders.show', $order->order_number)">{{ $order->order_number }}</flux:link>
                            </h2>
                            <strong><x-customer.vnd :amount="$order->total_vnd" /></strong>
                        </div>
                        <dl @class(['grid gap-x-4 gap-y-2 text-sm', 'sm:grid-cols-4' => $showOrderStatus, 'sm:grid-cols-3' => ! $showOrderStatus])>
                            <div>
                                <dt class="font-medium">Giờ đặt</dt>
                                <dd><x-customer.order-time :date="$order->created_at" /></dd>
                            </div>
                            <div>
                                <dt class="font-medium">Cách nhận</dt>
                                <dd>{{ $order->fulfillment_type->label() }}</dd>
                            </div>
                            @if ($showOrderStatus)
                                <div>
                                    <dt class="font-medium">Trạng thái đơn</dt>
                                    <dd>{{ $order->status->label() }}</dd>
                                </div>
                            @endif
                            <div>
                                <dt class="font-medium">Thanh toán</dt>
                                <dd>{{ $order->payment?->status->label() ?? 'Chưa có thông tin' }}</dd>
                            </div>
                        </dl>
                    </li>
                @endforeach
            </ul>
        @endif

        {{ $orders->links() }}
    </section>
</x-layouts::admin>
