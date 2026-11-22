@php($invoiceManagement = request()->routeIs('admin.invoices.*'))
<x-layouts::admin :title="'Quản lý đơn '.$invoice->number">
    <section class="mx-auto max-w-3xl space-y-5">
        <header data-no-print class="space-y-3">
            <flux:link :href="route($invoiceManagement ? 'admin.invoices.index' : 'admin.orders.index')">{{ $invoiceManagement ? 'Về quản lý hóa đơn' : 'Về danh sách đơn' }}</flux:link>
            <h1 class="text-2xl font-semibold">Đơn tại quầy {{ $invoice->number }}</h1>
        </header>
        <section class="terrasse-panel space-y-4 rounded-md p-4" data-no-print aria-labelledby="update-order-status">
            <h2 id="update-order-status" class="text-lg font-semibold">{{ $invoiceManagement ? 'Thay đổi trạng thái hóa đơn' : 'Cập nhật trạng thái đơn hàng' }}</h2>
            <p>Trạng thái hiện tại: <x-admin.order-state :state="$invoice->order_status->value === 'cancelled' ? 'cancelled' : ($invoice->status === 'paid' ? 'paid' : 'pending')" /></p>
            @if(in_array($invoice->status, ['draft', 'paid'], true))
                <form method="POST" action="{{ route('admin.counter-orders.preview', $invoice) }}" class="space-y-3">
                    @csrf
                    <input type="hidden" name="return_to_invoices" value="{{ $invoiceManagement ? 1 : 0 }}">
                    <input type="hidden" name="revision" value="{{ $invoice->revision }}">
                    <label class="block" for="counter-target">Trạng thái mới</label>
                    <select id="counter-target" name="target" required class="w-full rounded border p-2">
                        <option value="">Chọn trạng thái mới</option>
                        @if($invoice->status !== 'paid' || $invoice->order_status->value !== 'completed')<option value="completed" @selected(old('target') === 'completed')>Hoàn tất</option>@endif
                        @if($invoice->order_status->value !== 'cancelled')<option value="cancelled" @selected(old('target') === 'cancelled')>Đã hủy</option>@endif
                    </select>
                    <label class="block" for="counter-reason">{{ $invoice->status === 'paid' ? 'Lý do thay đổi (bắt buộc)' : 'Lý do hủy (bắt buộc khi hủy)' }}</label>
                    <textarea @required($invoice->status === 'paid') id="counter-reason" name="reason" maxlength="500" class="w-full rounded border p-2">{{ old('reason') }}</textarea>
                    @if($invoice->status === 'paid')<p>Hóa đơn đã thu được giữ nguyên. Hoàn tiền là thao tác riêng. Thay đổi này chỉ cập nhật trạng thái đơn, không thu thêm tiền hoặc hoàn tiền.</p>@endif
                    @if($invoice->status === 'draft' && $invoice->deposit_refund_vnd > 0)<label class="block"><input type="checkbox" name="deposit_refund_confirmed" value="1">Đã hoàn tiền mặt <x-customer.vnd :amount="$invoice->deposit_refund_vnd" /> cọc dư (bắt buộc khi hoàn tất).</label>@endif
                    <p class="text-sm">Bạn sẽ kiểm tra lại thay đổi ở bước xác minh trước khi hệ thống cập nhật.</p>
                    <flux:button type="submit" variant="primary">Tiếp tục xác minh</flux:button>
                </form>
            @else
                <label class="block" for="counter-final-status">Trạng thái đơn hàng</label>
                <select id="counter-final-status" disabled class="w-full rounded border p-2"><option>{{ $invoice->order_status->label() }}</option></select>
                <p>{{ $invoice->status === 'paid' ? 'Hóa đơn đã thu được giữ nguyên. Hoàn tiền là thao tác riêng.' : 'Đơn đã hủy, không thể mở lại hoặc thu tiền.' }}</p>
            @endif
        </section>
        @if($invoice->revisions->whereNotNull('after.order_status')->isNotEmpty())
            <section class="terrasse-panel space-y-3 rounded p-4" data-no-print>
                <h2 class="text-lg font-semibold">Lịch sử thay đổi trạng thái</h2>
                @foreach($invoice->revisions as $change)
                    @if(isset($change->after['order_status']))
                        <p><x-customer.order-time :date="$change->created_at" /> · {{ $change->actor?->name ?? 'Hệ thống' }} · {{ App\Enums\CounterOrderStatus::from($change->before['order_status'])->label() }} → {{ App\Enums\CounterOrderStatus::from($change->after['order_status'])->label() }} · {{ $change->after['reason'] }}</p>
                    @endif
                @endforeach
            </section>
        @endif
        @include('table-orders.invoice', ['adminInvoice' => true])
        @if($invoice->round->events->isNotEmpty())
            <section class="terrasse-panel space-y-2 rounded p-4" data-no-print>
                <h2 class="font-semibold">Lịch sử trạng thái</h2>
                @foreach($invoice->round->events as $event)
                    <p class="text-sm"><x-customer.order-time :date="$event->created_at" /> · {{ $event->actor?->name ?? 'Hệ thống' }} · {{ $event->to_status->label() }} @if($event->reason) · {{ $event->reason }} @endif</p>
                @endforeach
            </section>
        @endif
    </section>
</x-layouts::admin>
