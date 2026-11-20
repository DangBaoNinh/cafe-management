<x-layouts::customer title="Đơn của tôi">
    <section class="mx-auto max-w-3xl space-y-6">
        <h1 class="text-3xl font-semibold tracking-tight sm:text-4xl">Đơn của tôi</h1>

        <nav aria-label="Lọc đơn theo trạng thái" class="flex flex-wrap gap-2">
            @foreach ($filters as $value => $label)
                <a href="{{ route('orders.index', $value === 'all' ? [] : ['status' => $value]) }}"
                    @if ($filter === $value) aria-current="page" @endif
                    class="rounded-lg border border-coffee/20 px-3 py-2 text-sm font-medium hover:underline aria-[current=page]:bg-coffee aria-[current=page]:text-white">
                    {{ $label }}
                </a>
            @endforeach
        </nav>

        @if ($orders->isEmpty())
            <div class="space-y-3 rounded-xl border border-coffee/15 bg-white p-5">
                <p>{{ match (true) {
                    $orders->total() > 0 => 'Trang này không có đơn hàng.',
                    $filter === 'processing' => 'Bạn không có đơn đang xử lý.',
                    $filter === 'finished' => 'Bạn không có đơn đã kết thúc.',
                    default => 'Bạn chưa có đơn hàng.',
                } }}</p>
                @if ($filter === 'all' && $orders->total() === 0)
                    <flux:link :href="route('menu.index')">Xem thực đơn</flux:link>
                @endif
            </div>
        @else
            <ul class="divide-y divide-coffee/15 rounded-xl border border-coffee/15 bg-white">
                @foreach ($orders as $order)
                    <li class="space-y-3 p-4 sm:p-5">
                        <div class="flex flex-wrap items-baseline justify-between gap-2">
                            <h2 class="min-w-0 font-semibold wrap-anywhere">
                                <flux:link :href="route('orders.show', $order->order_number)">{{ $order->order_number }}</flux:link>
                            </h2>
                            <strong class="tabular-nums"><x-customer.vnd :amount="$order->total_vnd" /></strong>
                        </div>
                        <dl class="grid gap-x-4 gap-y-2 text-sm sm:grid-cols-3">
                            <div>
                                <dt class="font-medium">Ngày đặt</dt>
                                <dd><x-customer.order-time :date="$order->created_at" /></dd>
                            </div>
                            <div>
                                <dt class="font-medium">{{ $order->fulfillment_type->label() }}</dt>
                                <dd>{{ $order->status->label() }}</dd>
                            </div>
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
</x-layouts::customer>
