<x-layouts::admin :title="$title">
    <section class="space-y-6">
        <header class="space-y-2 border-b border-terrasse-ink/15 pb-5">
            <h1 class="font-semibold">{{ $title }}</h1>
            <p class="terrasse-muted text-sm">{{ number_format($orders->total(), 0, ',', '.') }} đơn hàng</p>
        </header>

        @if (isset($handoffView))
            <nav class="flex flex-wrap gap-3" aria-label="Trạng thái bàn giao">
                <a class="pill {{ $handoffView === 'active' ? 'active' : '' }}" href="{{ route('operations.handoff.index') }}" @if($handoffView === 'active') aria-current="page" @endif>Chờ bàn giao / Đang giao</a>
                <a class="pill {{ $handoffView === 'completed' ? 'active' : '' }}" href="{{ route('operations.handoff.index', ['view' => 'completed']) }}" @if($handoffView === 'completed') aria-current="page" @endif>Đã hoàn tất bàn giao</a>
            </nav>
        @endif
        <x-auth-session-status :status="session('status')" />
        @if($errors->any())
            <div role="alert" class="rounded-xl border border-terrasse-danger/30 p-4 text-terrasse-danger">@foreach($errors->all() as $message)<p>{{ $message }}</p>@endforeach</div>
        @endif

        @if ($orders->isEmpty())
            <p class="rounded-xl border border-terrasse-ink/15 bg-terrasse-paper p-5">
                {{ $orders->total() > 0 ? 'Trang này không có đơn hàng.' : $emptyMessage }}
            </p>
        @else
            <ul class="grid gap-4 md:grid-cols-2">
                @foreach ($orders as $order)
                    <li class="min-w-0">
                        <x-terrasse.order-card :$order :href="route('operations.orders.show', $order->order_number)" :amount="$order->total_vnd">
                            @if (($preparationQueue ?? false) && $order->status === \App\Enums\OrderStatus::Preparing)
                                <x-slot:actions>
                                    <form method="POST" action="{{ route('operations.orders.prepare', $order->order_number) }}">
                                        @csrf
                                        <input type="hidden" name="expected_status" value="preparing">
                                        <input type="hidden" name="target_status" value="ready">
                                        <input type="hidden" name="return_to" value="preparation">
                                        <flux:button type="submit" variant="primary" size="sm" :aria-label="'Hoàn tất pha chế đơn '.$order->order_number">Đã hoàn tất</flux:button>
                                    </form>
                                </x-slot:actions>
                            @endif
                            @if ($order->completed_at !== null)
                                <p class="text-sm">Đã bàn giao lúc <x-customer.order-time :date="$order->completed_at" /></p>
                                <flux:link :href="route('operations.invoices.orders.show', $order->order_number)">Xem hóa đơn</flux:link>
                            @endif
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
