<x-layouts::admin :title="$title">
    <section class="space-y-6">
        <header class="space-y-2 border-b border-coffee/15 pb-5">
            <h1 class="font-semibold">{{ $title }}</h1>
            <p class="terrasse-muted text-sm">{{ number_format($orders->total(), 0, ',', '.') }} đơn hàng</p>
        </header>

        @if ($orders->isEmpty())
            <p class="rounded-xl border border-coffee/15 bg-white p-5">
                {{ $orders->total() > 0 ? 'Trang này không có đơn hàng.' : $emptyMessage }}
            </p>
        @else
            <ul class="grid gap-4 md:grid-cols-2">
                @foreach ($orders as $order)
                    <li class="min-w-0">
                        <x-terrasse.order-card :$order :href="route('operations.orders.show', $order->order_number)" :amount="$order->total_vnd">
                            @if ($order->items->isNotEmpty())
                                <p class="terrasse-muted text-sm wrap-anywhere">
                                    @foreach ($order->items->sortBy('id')->take(3) as $item)
                                        {{ $item->quantity }} × {{ $item->product_name }}@unless($loop->last) · @endunless
                                    @endforeach
                                    @if ($order->items->count() > 3)
                                        · +{{ $order->items->count() - 3 }} dòng món
                                    @endif
                                </p>
                            @endif
                            <dl class="grid grid-cols-2 gap-3 text-sm">
                                <div>
                                    <dt class="terrasse-muted text-xs">Cách nhận</dt>
                                    <dd class="mt-1 font-semibold">{{ $order->fulfillment_type->label() }}</dd>
                                </div>
                                <div>
                                    <dt class="terrasse-muted text-xs">Thanh toán</dt>
                                    <dd class="mt-1 font-semibold">{{ $order->payment?->status->label() ?? 'Chưa có thông tin' }}</dd>
                                    @if ($order->payment)
                                        <dd class="terrasse-muted mt-1 text-xs">{{ $order->payment->method->label() }}</dd>
                                    @endif
                                </div>
                            </dl>
                        </x-terrasse.order-card>
                    </li>
                @endforeach
            </ul>
        @endif

        {{ $orders->links() }}
    </section>
</x-layouts::admin>
