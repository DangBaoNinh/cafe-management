<x-layouts::admin title="Xác minh cập nhật trạng thái">
    <section class="mx-auto max-w-2xl space-y-5">
        <h1 class="text-2xl font-semibold">Xác minh cập nhật trạng thái</h1>
        <div class="terrasse-panel space-y-4 rounded-md p-5">
            <p>Vui lòng kiểm tra thông tin trước khi xác nhận. Đơn hàng chưa được thay đổi.</p>
            <dl class="grid grid-cols-[auto_1fr] gap-x-5 gap-y-3">
                <dt>Mã đơn</dt><dd class="font-bold">{{ $invoice->number }}</dd>
                <dt>Bàn / phòng</dt><dd>{{ $invoice->tableSession->seat->code }}</dd>
                <dt>Hiện tại</dt><dd>{{ $invoice->order_status->label() }}</dd>
                <dt>Chuyển sang</dt><dd>{{ App\Enums\CounterOrderStatus::from($target)->label() }}</dd>
                <dt>Tổng tiền</dt><dd class="font-bold"><x-customer.vnd :amount="$invoice->total_vnd" /></dd>
                <dt>Phương thức</dt><dd>{{ $invoice->method->label() }}</dd>
                @if($reason)<dt>Lý do</dt><dd class="wrap-anywhere">{{ $reason }}</dd>@endif
            </dl>
            <p class="font-semibold">{{ $invoice->status === 'paid' ? 'Chỉ thay đổi trạng thái đơn. Giữ nguyên hóa đơn, tiền đã thu, ngày thu và phương thức thanh toán. Hoàn tiền xử lý riêng.' : ($target === 'completed' ? 'Xác nhận đồng nghĩa bạn đã kiểm tra và thu đủ tiền, hoàn tất đơn hàng và xuất hóa đơn.' : 'Xác nhận sẽ hủy đơn chưa thanh toán và lưu lý do vào lịch sử.') }}</p>
            @if($invoice->status === 'paid')
                <p>{{ $target === 'cancelled' ? 'Hóa đơn này sẽ được loại khỏi tổng doanh thu và thống kê tiền mặt/chuyển khoản.' : 'Hóa đơn này sẽ được tính lại vào tổng doanh thu và thống kê tiền mặt/chuyển khoản.' }}</p>
            @endif
            @include('table-orders.feedback')
            <form method="POST" action="{{ route('admin.counter-orders.update', $invoice) }}" class="flex flex-wrap items-center gap-4">
                @csrf
                <input type="hidden" name="return_to_invoices" value="{{ $returnToInvoices ? 1 : 0 }}">
                <input type="hidden" name="confirmation_token" value="{{ $token }}">
                <flux:button type="submit" variant="{{ $target === 'completed' ? 'primary' : 'danger' }}">Xác nhận cập nhật trạng thái</flux:button>
                <flux:link :href="route($returnToInvoices ? 'admin.invoices.show' : 'admin.orders.show', $invoice->number)">Hủy thao tác / quay lại</flux:link>
            </form>
        </div>
    </section>
</x-layouts::admin>
