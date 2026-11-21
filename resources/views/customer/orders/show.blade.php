@php
    $payment = $order->payment;
@endphp

<x-layouts::customer :title="'Đơn '.$order->order_number">
    <section class="mx-auto max-w-3xl space-y-6">
        <header class="space-y-2">
            <flux:link :href="route('orders.index')">Về Đơn của tôi</flux:link>
            <h1 class="text-3xl font-semibold tracking-tight wrap-anywhere sm:text-4xl">Đơn {{ $order->order_number }}</h1>
            <p class="text-sm">Đặt lúc <x-customer.order-time :date="$order->created_at" /></p>
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
                <dt class="text-sm">Thanh toán</dt>
                <dd class="font-semibold">{{ $payment?->status->label() ?? 'Chưa có thông tin' }}</dd>
                @if ($payment !== null)
                    <dd class="text-sm">{{ $payment->method->label() }}</dd>
                @endif
            </div>
        </dl>

        @if ($payment?->status === \App\Enums\PaymentStatus::RefundPending)
            <p class="rounded-xl border border-terrasse-ink/15 bg-terrasse-paper p-4">
                @if ($payment->method === \App\Enums\PaymentMethod::DemoGateway)
                    Giao dịch mô phỏng đang chờ quản trị viên ghi nhận hoàn tiền.
                @else
                    Cửa hàng sẽ xử lý hoàn tiền thủ công. Vui lòng liên hệ cửa hàng và cung cấp mã đơn {{ $order->order_number }} để được hỗ trợ.
                @endif
            </p>
        @endif

        @if ($payment?->method === \App\Enums\PaymentMethod::BankTransfer
            && $payment->status === \App\Enums\PaymentStatus::Pending && $order->total_vnd > 0)
            <flux:link :href="route('checkout.confirmation', $order->order_number)">Xem hướng dẫn chuyển khoản</flux:link>
        @endif

        <x-customer.start-demo-payment :order="$order" :payment="$payment" />

        @if ($order->status === \App\Enums\OrderStatus::Pending)
            <details class="rounded-xl border border-terrasse-ink/15 bg-terrasse-paper p-4" @if ($errors->hasAny(['reason', 'confirm'])) open @endif>
                <summary class="cursor-pointer font-semibold">Hủy đơn hàng</summary>
                <form method="POST" action="{{ route('orders.cancel', $order->order_number) }}" class="mt-4 space-y-4">
                    @csrf
                    <input type="hidden" name="expected_status" value="{{ $order->status->value }}">
                    <p class="text-sm">Bạn có chắc muốn hủy đơn này? Sau khi xác nhận, đơn sẽ không được tiếp tục xử lý.</p>
                    <div class="space-y-2">
                        <label class="block text-sm font-medium" for="cancel-reason">Lý do hủy (không bắt buộc)</label>
                        <flux:textarea id="cancel-reason" name="reason" maxlength="500" rows="2">{{ is_string(old('reason')) ? old('reason') : '' }}</flux:textarea>
                        <flux:error name="reason" />
                    </div>
                    <flux:button type="submit" name="confirm" value="1" variant="danger">Xác nhận hủy đơn</flux:button>
                    <flux:error name="confirm" />
                </form>
            </details>
        @endif

        <x-customer.order-snapshot :order="$order" />

        @if ($order->statusHistories->isNotEmpty())
            <div class="space-y-3">
                <h2 class="text-lg font-semibold">Tiến trình đơn</h2>
                <ol class="divide-y divide-terrasse-ink/15 rounded-xl border border-terrasse-ink/15 bg-terrasse-paper">
                    @foreach ($order->statusHistories as $history)
                        <li class="flex flex-wrap items-baseline justify-between gap-2 p-4">
                            <span class="font-medium">{{ \App\Enums\OrderStatus::from($history->to_status)->label() }}</span>
                            <span class="text-sm"><x-customer.order-time :date="$history->created_at" /></span>
                        </li>
                    @endforeach
                </ol>
            </div>
        @endif
    </section>
</x-layouts::customer>
