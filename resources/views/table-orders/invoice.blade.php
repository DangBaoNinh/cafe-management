@php($adminInvoice = $adminInvoice ?? false)
<article class="invoice-sheet receipt-page">
    <div data-no-print>@include('table-orders.feedback')</div>
    <div class="receipt-toolbar" data-no-print>
        @if(!$adminInvoice)<flux:link :href="$customer ? route('table-orders.show', $invoice->tableSession->seat->code) : route('operations.table-sessions.index')">{{ $customer ? 'Về bàn' : 'Quay về nhận order' }}</flux:link>@endif
        <flux:button type="button" data-print-invoice>In {{ $invoice->status === 'paid' ? 'hóa đơn' : 'tạm tính' }}</flux:button>
        @if(!$adminInvoice && !$customer && $invoice->status === 'draft')<flux:button :href="route('operations.table-sessions.pos', [$invoice->tableSession, 'round' => $invoice->table_round_id])">Sửa order</flux:button>@endif
    </div>
    <section class="receipt-paper" aria-label="{{ $invoice->status === 'paid' ? 'Hóa đơn thanh toán' : 'Hóa đơn tạm tính' }}">
        <x-receipt.header :title="match($invoice->status) { 'paid' => 'Hóa đơn thanh toán', 'voided' => 'Hóa đơn đã hủy', default => 'Hóa đơn tạm tính' }" :number="$invoice->number" />
        <dl class="receipt-meta">
            <div><dt>Bàn / phòng</dt><dd>{{ $invoice->tableSession->seat->code }} · Tầng {{ $invoice->tableSession->seat->floor }}</dd></div>
            <div><dt>Ngày lập</dt><dd><x-customer.order-time :date="$invoice->created_at" /></dd></div>
            @if($invoice->paid_at)<div><dt>Thanh toán lúc</dt><dd><x-customer.order-time :date="$invoice->paid_at" /></dd></div>@endif
            <div><dt>Trạng thái đơn:</dt><dd><strong>{{ $invoice->order_status->label() }}</strong></dd></div>
            <div><dt>Phương thức</dt><dd>{{ $invoice->method->label() }}</dd></div>
            <div><dt>Thanh toán</dt><dd><strong>{{ $invoice->status === 'paid' ? 'Đã thanh toán' : ($invoice->status === 'voided' ? 'Đã hủy' : 'Chưa thanh toán') }}</strong></dd></div>
        </dl>
        <p class="receipt-currency">Đơn vị tính: đồng (₫)</p>
        <x-receipt.items>
            @foreach($invoice->round->items as $item)<x-receipt.item :$item />@endforeach
        </x-receipt.items>
        <dl class="receipt-totals">
            <div><dt>Tổng số lượng</dt><dd>{{ $invoice->round->items->sum('quantity') }}</dd></div>
            <div><dt>Tiền món</dt><dd><x-customer.vnd :amount="$invoice->subtotal_vnd" /></dd></div>
            @foreach($invoice->fees as $fee)<div><dt>{{ $fee->label() }}</dt><dd><x-customer.vnd :amount="$fee->amount_vnd" /></dd></div>@endforeach
            @if($invoice->deposit_credit_vnd > 0)
                <div><dt>Tổng tiền món và phụ phí</dt><dd><x-customer.vnd :amount="$invoice->total_vnd" /></dd></div>
                <div><dt>Trừ tiền cọc</dt><dd>−<x-customer.vnd :amount="$invoice->deposit_credit_vnd" /></dd></div>
            @endif
            <div class="receipt-grand-total"><dt>{{ $invoice->deposit_credit_vnd > 0 ? 'Thanh toán thêm' : ($invoice->status === 'paid' ? 'Tổng đã thanh toán' : 'Tổng tạm tính') }}</dt><dd><x-customer.vnd :amount="$invoice->amount_due_vnd" /></dd></div>
            @if($invoice->deposit_refund_vnd > 0)<div><dt>{{ $invoice->status === 'paid' ? 'Đã hoàn cọc dư bằng tiền mặt' : 'Cần hoàn cọc dư bằng tiền mặt' }}</dt><dd><x-customer.vnd :amount="$invoice->deposit_refund_vnd" /></dd></div>@endif
        </dl>
        @if($qrImage && $invoice->status === 'draft')
            <section class="receipt-payment">
                <h2>Quét QR để chuyển khoản</h2>
                <div class="receipt-qr {{ str_ends_with($qrImage, '/payments/transfer-qr.png') ? 'receipt-qr--poster' : '' }}"><img src="{{ $qrImage }}" alt="QR chuyển khoản {{ $invoice->number }}"></div>
                <p><strong>{{ $invoice->bank_details['bank_name'] }}</strong> · {{ $invoice->bank_details['account_number'] }}</p>
                <p>{{ $invoice->bank_details['account_holder'] }}</p>
                <p>Nội dung: <strong>{{ $invoice->number }}</strong></p>
                <p class="receipt-small">Nhập đúng số tiền và nội dung trên. Kiểm tra người nhận trước khi chuyển khoản.</p>
            </section>
        @endif
        <footer class="receipt-footer">
            @if($invoice->status === 'draft')<p>Phiếu tạm tính chưa ghi nhận thanh toán.</p>@endif
            @if($invoice->status === 'voided')<p>Đơn đã hủy — không thực hiện thanh toán.</p>@endif
            <p><strong>Cảm ơn Quý khách!</strong></p><p>Hẹn gặp lại tại Toàn Café &amp; Jardin.</p>
        </footer>
    </section>
    @if(!$adminInvoice && !$customer && $invoice->status === 'draft')
        <section class="receipt-actions" data-no-print>
            <p>Kiểm tra tạm tính và xác nhận đã thu đủ tiền {{ $invoice->method->label() }}.</p>
            <form method="POST" action="{{ route('operations.invoices.prepaid.pay', $invoice) }}">@csrf<input type="hidden" name="revision" value="{{ $invoice->revision }}">@if($invoice->deposit_refund_vnd > 0)<label class="block"><input type="checkbox" name="deposit_refund_confirmed" value="1" required> Đã hoàn tiền mặt <x-customer.vnd :amount="$invoice->deposit_refund_vnd" /> cọc dư.</label>@endif<flux:button type="submit" variant="primary">Hoàn tất thanh toán</flux:button></form>
        </section>
    @endif
    @if($customer && $invoice->status === 'draft')
        <section class="receipt-actions" data-no-print>
            @if($invoice->method === \App\Enums\CollectionMethod::BankTransfer)
                @if($invoice->payment_reported_at)<p role="status">Đã báo chuyển khoản. Đang chờ nhân viên xác nhận tiền vào tài khoản.</p>@else
                    <form method="POST" action="{{ route('table-orders.invoice.report', [$invoice->tableSession->seat->code, $invoice]) }}">@csrf<flux:button type="submit" variant="primary">Tôi đã chuyển khoản</flux:button></form>
                @endif
            @else<p>Vui lòng thanh toán tiền mặt với nhân viên để xác nhận order.</p>@endif
            <flux:link :href="route('table-orders.invoice.show', [$invoice->tableSession->seat->code, $invoice])">Cập nhật thanh toán</flux:link>
        </section>
    @endif
    @if(!$adminInvoice && !$customer && $invoice->status === 'paid')<div class="receipt-actions" data-no-print><flux:button variant="primary" :href="route('operations.table-sessions.index')">Quay về nhận order</flux:button></div>@endif
    @if(!$adminInvoice && $invoice->status === 'paid')<p role="status" data-no-print>Đã hoàn tất thanh toán và xuất hóa đơn. Trạng thái bàn: {{ $invoice->tableSession->seat->availabilityLabel() }}.</p>@endif
</article>
