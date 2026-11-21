<x-layouts::admin :title="'Đơn '.$order->order_number">
    <section class="mx-auto max-w-3xl space-y-6">
        <header class="space-y-2">
            <flux:link :href="route('operations.orders.index')">Về hàng đợi</flux:link>
            <h1 class="text-2xl font-semibold wrap-anywhere sm:text-3xl">Đơn {{ $order->order_number }}</h1>
            <p class="text-sm">Đặt lúc <x-customer.order-time :date="$order->created_at" /></p>
            @if ($order->completed_at !== null)
                <p class="text-sm">Hoàn tất lúc <x-customer.order-time :date="$order->completed_at" /></p>
            @endif
        </header>

        @if (session('status'))
            <p role="status" class="rounded-lg border border-terrasse-ink/15 bg-terrasse-paper p-4 text-sm">{{ session('status') }}</p>
        @endif
        @foreach (['expected_status', 'decision', 'target_status', 'payment', 'fulfillment_type', 'method', 'shipment', 'event'] as $field)
            @error($field)
                <p role="alert" class="rounded-lg border border-terrasse-danger/30 bg-terrasse-danger-surface p-4 text-sm text-terrasse-danger">{{ $message }}</p>
            @enderror
        @endforeach

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
                <dd class="font-semibold">{{ $order->payment?->status->label() ?? 'Chưa có thông tin' }}</dd>
                @if ($order->payment !== null)
                    <dd class="text-sm">{{ $order->payment->method->label() }}</dd>
                    <dd class="mt-1 text-sm"><flux:link :href="route('operations.payments.show', $order->order_number)">Chi tiết thanh toán</flux:link></dd>
                @endif
            </div>
        </dl>

        <x-customer.order-snapshot :order="$order" />

        @if ($pickup = $order->scheduledPickup)
            <p class="text-sm">
                Hẹn nhận lúc <x-customer.order-time :date="$pickup->scheduled_at" />
                · <flux:link :href="route('operations.pickups.show', $pickup->code)">Tra cứu phiếu nhận</flux:link>
            </p>
        @endif

        @if ($order->status === \App\Enums\OrderStatus::Pending)
            <section class="space-y-4 rounded-xl border border-terrasse-ink/15 bg-terrasse-paper p-4">
                <h2 class="text-lg font-semibold">Quyết định tiếp nhận</h2>
                <form method="POST" action="{{ route('operations.orders.decide', $order->order_number) }}">
                    @csrf
                    <input type="hidden" name="expected_status" value="{{ $order->status->value }}">
                    <input type="hidden" name="decision" value="{{ \App\Enums\OrderStatus::Confirmed->value }}">
                    <flux:button type="submit" variant="primary">Xác nhận đơn</flux:button>
                </form>
                <details class="border-t border-terrasse-ink/15 pt-4" @error('reason') open @enderror>
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
        @elseif (in_array($order->status, [\App\Enums\OrderStatus::Confirmed, \App\Enums\OrderStatus::Preparing], true))
            @include('operations.orders.preparation-actions')
        @elseif ($pickup === null && $order->demoShipment === null && ($handoffTarget = $order->nextHandoffStatus()) !== null)
            @include('operations.orders.handoff-actions', ['target' => $handoffTarget])
        @endif

        @if (\App\Services\DemoDeliveryProvider::enabled() && $order->fulfillment_type === \App\Enums\FulfillmentType::Delivery)
            @include('operations.orders.demo-shipment', ['shipment' => $order->demoShipment])
        @endif
    </section>
</x-layouts::admin>
