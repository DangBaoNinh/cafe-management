<x-layouts::admin :title="'Hóa đơn '.$order->order_number">
    <article class="invoice-sheet receipt-page">
        @if(session('status'))<p role="status" data-no-print>{{ session('status') }}</p>@endif
        <div class="receipt-toolbar" data-no-print>
            <flux:link :href="route($order->payment->paid_at ? 'operations.invoices.history' : 'operations.invoices.index')">Về danh sách hóa đơn</flux:link>
            <flux:button type="button" data-print-invoice>In hóa đơn</flux:button>
        </div>
        <section class="receipt-paper">
            <x-receipt.header :title="$order->payment->paid_at ? 'Hóa đơn thanh toán' : 'Hóa đơn tạm tính'" :number="$order->order_number" />
            <dl class="receipt-meta">
                <div><dt>Ngày lập</dt><dd><x-customer.order-time :date="$order->created_at" /></dd></div>
                <div><dt>Khách hàng</dt><dd>{{ $order->recipient_name }}</dd></div>
                <div><dt>Điện thoại</dt><dd>{{ $order->phone }}</dd></div>
                @if($order->address_snapshot)<div><dt>Địa chỉ</dt><dd>{{ implode(', ', array_filter([$order->address_snapshot['address_line'] ?? null, $order->address_snapshot['ward_name'] ?? null, $order->address_snapshot['district_name'] ?? null, $order->address_snapshot['province_name'] ?? null])) }}</dd></div>@endif
                <div><dt>Cách nhận</dt><dd>{{ $order->fulfillment_type->label() }}</dd></div>
                <div><dt>Trạng thái đơn</dt><dd>{{ $order->status->label() }}</dd></div>
                <div><dt>Phương thức</dt><dd>{{ $order->payment->method->label() }}</dd></div>
                <div><dt>Thanh toán</dt><dd><strong>{{ $order->payment->status->label() }}</strong></dd></div>
                @if($order->payment->collection_method)<div><dt>Hình thức thực thu</dt><dd>{{ $order->payment->collection_method->label() }}</dd></div>@endif
                @if($order->payment->paid_at)<div><dt>Thanh toán lúc</dt><dd><x-customer.order-time :date="$order->payment->paid_at" /></dd></div>@endif
                @if($order->payment->refunded_at)<div><dt>Hoàn tiền lúc</dt><dd><x-customer.order-time :date="$order->payment->refunded_at" /></dd></div>@endif
            </dl>
            <p class="receipt-currency">Đơn vị tính: đồng (₫)</p>
            <x-receipt.items>@foreach($order->items as $item)<x-receipt.item :$item :order-item="true" />@endforeach</x-receipt.items>
            <dl class="receipt-totals">
                <div><dt>Tổng số lượng</dt><dd>{{ $order->items->sum('quantity') }}</dd></div>
                <div><dt>Tạm tính</dt><dd><x-customer.vnd :amount="$order->subtotal_vnd" /></dd></div>
                <div><dt>Giảm giá</dt><dd><x-customer.vnd :amount="$order->discount_vnd" /></dd></div>
                <div><dt>Phí giao</dt><dd><x-customer.vnd :amount="$order->delivery_fee_vnd" /></dd></div>
                <div class="receipt-grand-total"><dt>Tổng tiền</dt><dd><x-customer.vnd :amount="$order->total_vnd" /></dd></div>
            </dl>
            @if($order->payment->status === \App\Enums\PaymentStatus::Pending && $order->payment->transfer_details)<div class="receipt-payment"><x-payment-qr :amount="$order->total_vnd" :memo="$order->order_number" :bank="$order->payment->transfer_details" /></div>@endif
            @if($order->scheduledPickup?->fees->isNotEmpty())
                <section class="receipt-extra"><h2>Phụ phí lịch hẹn (theo dõi riêng)</h2><p class="receipt-small">Các khoản dưới đây không nằm trong tổng tiền đơn ở trên.</p>
                    <dl class="receipt-totals">@foreach($order->scheduledPickup->fees as $fee)<div><dt>{{ $fee->label() }} · {{ $fee->statusLabel() }} · {{ \App\Models\PickupFee::CHANNELS[$fee->channel] }}</dt><dd><x-customer.vnd :amount="(int) $fee->amount_vnd" /></dd></div>@endforeach</dl>
                </section>
            @endif
            <footer class="receipt-footer"><p><strong>Cảm ơn Quý khách!</strong></p><p>Hẹn gặp lại tại Toàn Café &amp; Jardin.</p></footer>
        </section>
        <div class="receipt-actions" data-no-print>
            <flux:button :href="route('operations.payments.show', $order->order_number)">Xử lý thanh toán / đối chiếu</flux:button><flux:button :href="route('operations.orders.show', $order->order_number)">Xem đơn / bàn giao</flux:button>
            @if($order->scheduledPickup)<flux:button :href="route('operations.pickups.show', $order->scheduledPickup->code)">Phiếu nhận và phụ phí</flux:button>@endif
        </div>
    </article>
</x-layouts::admin>
