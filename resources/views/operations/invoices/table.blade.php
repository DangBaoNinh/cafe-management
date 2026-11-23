<x-layouts::admin :title="'Hóa đơn bàn BH-'.$tableSession->id">
    <article class="invoice-sheet receipt-page">
        <div class="receipt-toolbar" data-no-print><flux:link :href="route($tableSession->paid_at ? 'operations.invoices.history' : 'operations.invoices.index')">Về danh sách hóa đơn</flux:link><flux:button type="button" data-print-invoice>In hóa đơn</flux:button></div>
        <section class="receipt-paper">
            <x-receipt.header :title="$tableSession->paid_at ? 'Hóa đơn thanh toán' : 'Hóa đơn tạm tính'" :number="'BH-'.$tableSession->id" />
            <dl class="receipt-meta">
                <div><dt>Bàn / phòng</dt><dd>{{ $tableSession->seat->code }} · Tầng {{ $tableSession->seat->floor }}</dd></div>
                <div><dt>Ngày lập</dt><dd><x-customer.order-time :date="$tableSession->billed_at" /></dd></div>
                <div><dt>Thanh toán</dt><dd><strong>{{ $tableSession->paid_at ? 'Đã thanh toán tại quầy' : 'Chờ thanh toán tại quầy' }}</strong></dd></div>
                @if($tableSession->paid_at)<div><dt>Thanh toán lúc</dt><dd><x-customer.order-time :date="$tableSession->paid_at" /></dd></div>@endif
            </dl>
            <p class="receipt-currency">Đơn vị tính: đồng (₫)</p>
            <x-receipt.items>@foreach($rounds as $round)@foreach($round->items as $item)<x-receipt.item :$item />@endforeach@endforeach</x-receipt.items>
            @if($rounds->isEmpty())<p class="receipt-small">Không có món đã phục vụ.</p>@endif
            <dl class="receipt-totals">
                <div><dt>Tiền món</dt><dd><x-customer.vnd :amount="$tableSession->subtotal_vnd" /></dd></div>
                <div><dt>Phụ phí tại quầy</dt><dd><x-customer.vnd :amount="$tableSession->fees_vnd" /></dd></div>
                <div class="receipt-grand-total"><dt>{{ $tableSession->paid_at ? 'Tổng đã thanh toán' : 'Tổng tạm tính' }}</dt><dd><x-customer.vnd :amount="$tableSession->total_vnd" /></dd></div>
            </dl>
            <footer class="receipt-footer"><p><strong>Cảm ơn Quý khách!</strong></p><p>Hẹn gặp lại tại Toàn Café &amp; Jardin.</p></footer>
        </section>
        <div class="receipt-actions" data-no-print><flux:button :href="route('operations.table-sessions.show', $tableSession)">Mở hóa đơn bàn / trả chỗ</flux:button></div>
    </article>
</x-layouts::admin>
