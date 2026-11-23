@use('App\Enums\OrderStatus')
@use('App\Enums\PaymentStatus')

@php($payment = $order->payment)

<x-layouts::admin :title="'Quản lý đơn '.$order->order_number">
    <section class="mx-auto max-w-3xl space-y-6" data-admin-order-actions data-order-number="{{ $order->order_number }}" data-current-status="{{ $order->status->label() }}">
        <header class="space-y-2">
            <flux:link :href="route('admin.orders.index')">Về danh sách đơn</flux:link>
            <h1 class="text-2xl font-semibold wrap-anywhere sm:text-3xl">Đơn {{ $order->order_number }}</h1>
            @if (\App\Services\DemoDeliveryProvider::enabled() && $order->demoShipment !== null)
                <flux:link :href="route('operations.orders.show', $order->order_number)">Xem vận đơn mô phỏng</flux:link>
            @endif
            <p class="text-sm">Đặt lúc <x-customer.order-time :date="$order->created_at" /></p>
            @if ($order->completed_at !== null)
                <p class="text-sm">Hoàn tất lúc <x-customer.order-time :date="$order->completed_at" /></p>
            @endif
        </header>

        @if (session('status'))
            <p role="status" class="rounded-lg border border-terrasse-ink/15 bg-terrasse-paper p-4 text-sm">{{ session('status') }}</p>
        @endif
        @error('expected_status')
            <p role="alert" class="rounded-lg border border-terrasse-danger/30 bg-terrasse-danger-surface p-4 text-sm text-terrasse-danger">{{ $message }}</p>
        @enderror

        <dl class="grid gap-4 rounded-xl border border-terrasse-ink/15 bg-terrasse-paper p-4 sm:grid-cols-3">
            <div>
                <dt class="text-sm">Trạng thái đơn</dt>
                <dd class="font-semibold">{{ $order->status->label() }}</dd>
            </div>
            <div>
                <dt class="text-sm">Cách nhận</dt>
                <dd class="font-semibold">{{ $order->fulfillment_type->label() }}</dd>
            </div>
            <div>
                <dt class="text-sm">{{ $payment?->method->label() ?? 'Thanh toán' }}</dt>
                <dd class="font-semibold">{{ $payment?->status->label() ?? 'Chưa có thông tin' }}</dd>
                @if ($payment !== null)
                    <dd class="mt-1 text-sm">
                        <flux:link :href="route('operations.payments.show', $order->order_number)">
                            {{ $payment->status === PaymentStatus::RefundPending ? 'Ghi nhận hoàn tiền' : 'Chi tiết thanh toán' }}
                        </flux:link>
                    </dd>
                @endif
            </div>
        </dl>

        @if ($order->status->allowsAdminCancellation())
            <details class="rounded-xl border border-terrasse-ink/15 bg-terrasse-paper p-4" @error('reason') open @enderror>
                <summary class="cursor-pointer font-semibold">Hủy đơn ngoại lệ</summary>
                <form method="POST" action="{{ route('admin.orders.cancel', $order->order_number) }}" class="mt-4 space-y-3">
                    @csrf
                    <input type="hidden" name="expected_status" value="{{ $order->status->value }}">
                    <p class="text-sm">Đơn sẽ dừng xử lý. Khoản đã thu sẽ được chuyển sang chờ hoàn tiền.</p>
                    <label class="block text-sm font-medium" for="cancel-reason">Lý do hủy đơn</label>
                    <flux:textarea id="cancel-reason" name="reason" maxlength="500" rows="2" required>{{ is_string(old('reason')) ? old('reason') : '' }}</flux:textarea>
                    <flux:error name="reason" />
                    <flux:button type="submit" variant="danger">Xác nhận hủy đơn</flux:button>
                </form>
            </details>
        @endif

        <section class="space-y-3" aria-label="Điều chỉnh tiến trình đơn trực tuyến">
            <h2 class="text-lg font-semibold">Điều chỉnh tiến trình đơn</h2>
            @if($order->status === OrderStatus::Pending)
                <form method="POST" action="{{ route('operations.orders.decide', $order->order_number) }}">@csrf<input type="hidden" name="expected_status" value="pending"><input type="hidden" name="decision" value="confirmed"><flux:button type="submit" variant="primary">Xác nhận đơn hàng</flux:button></form>
            @elseif(in_array($order->status, [OrderStatus::Confirmed, OrderStatus::Preparing], true))
                @include('operations.orders.preparation-actions')
            @elseif($order->scheduledPickup !== null || $order->demoShipment !== null)
                <flux:link :href="route('operations.orders.show', $order->order_number)">Cập nhật nhận hàng / bàn giao</flux:link>
            @elseif(($target = $order->nextHandoffStatus()) !== null)
                @include('operations.orders.handoff-actions', ['target' => $target])
            @endif
        </section>
        <x-customer.order-snapshot :order="$order" />

        <div class="space-y-3">
            <h2 class="text-lg font-semibold">Lịch sử đơn hàng</h2>
            <x-admin.status-history :entries="$order->statusHistories" :label-for-status="fn (string $status) => OrderStatus::from($status)->label()" />
        </div>
        @if ($payment !== null)
            <div class="space-y-3">
                <h2 class="text-lg font-semibold">Lịch sử thanh toán</h2>
                <x-admin.status-history :entries="$payment->statusHistories" :label-for-status="fn (string $status) => PaymentStatus::from($status)->label()" />
            </div>
        @endif
        <dialog data-admin-status-dialog aria-labelledby="admin-status-dialog-title" class="m-auto w-full max-w-lg space-y-4 rounded-xl border border-terrasse-ink/15 bg-terrasse-paper p-6 text-terrasse-ink backdrop:bg-black/40">
            <h2 id="admin-status-dialog-title" class="text-xl font-semibold">Xác minh cập nhật trạng thái</h2>
            <p data-admin-status-summary class="wrap-anywhere"></p>
            <p>Kiểm tra thông tin trước khi xác nhận. Chỉ cập nhật sau khi bạn đồng ý.</p>
            <div class="flex flex-wrap gap-3">
                <flux:button type="button" variant="primary" data-admin-status-approve>Xác nhận cập nhật</flux:button>
                <flux:button type="button" data-admin-status-cancel>Quay lại</flux:button>
            </div>
        </dialog>
    </section>
</x-layouts::admin>
