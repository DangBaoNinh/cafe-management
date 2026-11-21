<x-layouts::admin :title="'Tiếp nhận đơn '.$order->order_number">
    <section class="mx-auto max-w-3xl space-y-6">
        <header class="space-y-2">
            <flux:link :href="route('operations.orders.index')">Về hàng đợi</flux:link>
            <h1 class="text-2xl font-semibold wrap-anywhere sm:text-3xl">Đơn {{ $order->order_number }}</h1>
            <p class="text-sm">Đặt lúc <x-customer.order-time :date="$order->created_at" /></p>
        </header>

        @if (session('status'))
            <p role="status" class="rounded-lg border border-coffee/15 bg-white p-4 text-sm">{{ session('status') }}</p>
        @endif
        @foreach (['expected_status', 'decision'] as $field)
            @error($field)
                <p role="alert" class="rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-800">{{ $message }}</p>
            @enderror
        @endforeach

        <dl class="grid gap-4 rounded-xl border border-coffee/15 bg-white p-4 sm:grid-cols-3">
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
                <dd class="font-semibold">{{ $order->payment?->status->label() ?? 'Chưa có thông tin' }}</dd>
                @if ($order->payment !== null)
                    <dd class="text-sm">{{ $order->payment->method->label() }}</dd>
                    <dd class="mt-1 text-sm"><flux:link :href="route('operations.payments.show', $order->order_number)">Chi tiết thanh toán</flux:link></dd>
                @endif
            </div>
        </dl>

        <x-customer.order-snapshot :order="$order" />

        @if ($order->status === \App\Enums\OrderStatus::Pending)
            <section class="space-y-4 rounded-xl border border-coffee/15 bg-white p-4">
                <h2 class="text-lg font-semibold">Quyết định tiếp nhận</h2>
                <form method="POST" action="{{ route('operations.orders.decide', $order->order_number) }}">
                    @csrf
                    <input type="hidden" name="expected_status" value="{{ $order->status->value }}">
                    <input type="hidden" name="decision" value="{{ \App\Enums\OrderStatus::Confirmed->value }}">
                    <flux:button type="submit" variant="primary">Xác nhận đơn</flux:button>
                </form>
                <details class="border-t border-coffee/15 pt-4" @error('reason') open @enderror>
                    <summary class="cursor-pointer font-semibold">Từ chối đơn</summary>
                    <form method="POST" action="{{ route('operations.orders.decide', $order->order_number) }}" class="mt-4 space-y-3">
                        @csrf
                        <input type="hidden" name="expected_status" value="{{ $order->status->value }}">
                        <input type="hidden" name="decision" value="{{ \App\Enums\OrderStatus::Rejected->value }}">
                        <label class="block text-sm font-medium" for="rejection-reason">Lý do từ chối</label>
                        <flux:textarea id="rejection-reason" name="reason" maxlength="500" rows="2" required>{{ is_string(old('reason')) ? old('reason') : '' }}</flux:textarea>
                        <flux:error name="reason" />
                        <flux:button type="submit" variant="danger">Xác nhận từ chối</flux:button>
                    </form>
                </details>
            </section>
        @endif
    </section>
</x-layouts::admin>
