<x-layouts::admin :title="'Hóa đơn đặt cọc '.$deposit->number">
    <div class="receipt-page space-y-4">
        <div class="receipt-toolbar" data-no-print><flux:link :href="route('admin.deposits.index')">Về quản lý tiền cọc</flux:link> <flux:button type="button" data-print-invoice>In chứng từ đặt cọc</flux:button></div>
        <article class="invoice-sheet receipt-paper">
            <x-receipt.header title="Chứng từ đặt cọc" :number="$deposit->number" />
            <dl class="receipt-meta">
                <div><dt>Bàn</dt><dd>{{ $deposit->reservation->seat->code }}</dd></div>
                <div><dt>Mã đặt</dt><dd>{{ $deposit->reservation->code }}</dd></div>
                <div><dt>Khách hàng</dt><dd>{{ $deposit->reservation->contact_name }}</dd></div>
                <div><dt>Giờ hẹn</dt><dd><x-customer.order-time :date="$deposit->reservation->scheduled_at" /></dd></div>
                <div><dt>Phương thức</dt><dd>Chuyển khoản</dd></div>
                <div><dt>Trạng thái</dt><dd>{{ $deposit->statusLabel() }}</dd></div>
                @if($deposit->forfeit_reason)<div><dt>Lý do giữ cọc</dt><dd>{{ $deposit->forfeit_reason }}</dd></div>@endif
                @if($deposit->paid_at)<div><dt>Đã nhận lúc</dt><dd><x-customer.order-time :date="$deposit->paid_at" /></dd></div>@endif
                @if($deposit->demo)<div><dt>Hình thức</dt><dd>Giao dịch demo</dd></div>@endif
            </dl>
            <dl class="receipt-totals">
                <div class="receipt-grand-total"><dt>Tiền cọc</dt><dd><x-customer.vnd :amount="$deposit->amount_vnd" /></dd></div>
                @if($deposit->status === 'settled')
                    <div><dt>Đã trừ hóa đơn {{ $deposit->invoice?->number }}</dt><dd><x-customer.vnd :amount="$deposit->credited_vnd" /></dd></div>
                    <div><dt>Đã hoàn dư bằng tiền mặt</dt><dd><x-customer.vnd :amount="$deposit->cash_refund_vnd" /></dd></div>
                @endif
            </dl>
            <p class="receipt-footer">Tiền cọc được sử dụng một lần. Chứng từ cọc không cộng trùng vào doanh thu bán món.</p>
        </article>
    </div>
</x-layouts::admin>
