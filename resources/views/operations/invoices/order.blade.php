<x-layouts::admin :title="'Hóa đơn '.$order->order_number">
    <article class="invoice-sheet mx-auto max-w-3xl space-y-6">
        @if(session('status'))<p role="status" data-no-print>{{ session('status') }}</p>@endif
        <div class="flex flex-wrap gap-3" data-no-print>
            <flux:link :href="route($order->payment->paid_at ? 'operations.invoices.history' : 'operations.invoices.index')">Về danh sách hóa đơn</flux:link>
            <flux:button type="button" data-print-invoice>In hóa đơn</flux:button>
        </div>
        <header class="space-y-2 border-b border-terrasse-ink/15 pb-4">
            <p class="terrasse-display text-3xl">Toàn Café &amp; Jardin</p>
            <h1 class="wrap-anywhere">Hóa đơn {{ $order->order_number }}</h1>
            <p>Lập lúc <x-customer.order-time :date="$order->created_at" /></p>
            <p>{{ $order->fulfillment_type->label() }} · {{ $order->status->label() }}</p>
            <p>{{ $order->payment->method->label() }} · <strong>{{ $order->payment->status->label() }}</strong></p>
            @if($order->payment->collection_method)<p>Hình thức thực thu: {{ $order->payment->collection_method->label() }}</p>@endif
            @if($order->payment->paid_at)<p>Thanh toán lúc <x-customer.order-time :date="$order->payment->paid_at" /></p>@endif
            @if($order->payment->refunded_at)<p>Hoàn tiền lúc <x-customer.order-time :date="$order->payment->refunded_at" /></p>@endif
        </header>
        <x-customer.order-snapshot :$order />
        @if($order->scheduledPickup?->fees->isNotEmpty())
            <section class="terrasse-panel space-y-3 p-4">
                <h2 class="text-lg font-semibold">Phụ phí lịch hẹn (theo dõi riêng)</h2>
                <p class="terrasse-muted text-sm">Các khoản dưới đây không nằm trong tổng tiền đơn ở trên.</p>
                @foreach($order->scheduledPickup->fees as $fee)
                    <div class="flex flex-wrap justify-between gap-2 text-sm"><span>{{ $fee->label() }} · {{ $fee->statusLabel() }} · {{ \App\Models\PickupFee::CHANNELS[$fee->channel] }}</span><x-customer.vnd :amount="(int) $fee->amount_vnd" /></div>
                @endforeach
            </section>
        @endif
        <div class="flex flex-wrap gap-3" data-no-print>
            <flux:button :href="route('operations.payments.show', $order->order_number)">Xử lý thanh toán / đối chiếu</flux:button>
            <flux:button :href="route('operations.orders.show', $order->order_number)">Xem đơn / bàn giao</flux:button>
            @if($order->scheduledPickup)<flux:button :href="route('operations.pickups.show', $order->scheduledPickup->code)">Phiếu nhận và phụ phí</flux:button>@endif
        </div>
    </article>
</x-layouts::admin>
