<x-layouts::admin :title="'Thanh toán đơn '.$payment->order->order_number">
    <section class="mx-auto max-w-3xl space-y-6">
        <header class="space-y-2">
            <flux:link :href="route('operations.payments.index')">Về đối chiếu thanh toán</flux:link>
            <h1 class="text-2xl font-semibold wrap-anywhere sm:text-3xl">Thanh toán đơn {{ $payment->order->order_number }}</h1>
            <flux:link :href="route('operations.orders.show', $payment->order->order_number)">Xem chi tiết đơn</flux:link>
            <flux:link :href="route('operations.invoices.orders.show', $payment->order->order_number)">Xem / in hóa đơn</flux:link>
        </header>

        @if (session('status'))
            <p role="status" class="rounded-lg border border-terrasse-ink/15 bg-terrasse-paper p-4 text-sm">{{ session('status') }}</p>
        @endif
        @foreach (['expected_status', 'method'] as $field)
            @error($field)
                <p role="alert" class="rounded-lg border border-terrasse-danger/30 bg-terrasse-danger-surface p-4 text-sm text-terrasse-danger">{{ $message }}</p>
            @enderror
        @endforeach

        <dl class="grid gap-4 rounded-xl border border-terrasse-ink/15 bg-terrasse-paper p-4 sm:grid-cols-2">
            <div>
                <dt class="text-sm">Trạng thái đơn</dt>
                <dd class="font-semibold">{{ $payment->order->status->label() }}</dd>
            </div>
            <div>
                <dt class="text-sm">Tổng tiền</dt>
                <dd class="font-semibold"><x-customer.vnd :amount="$payment->amount_vnd" /></dd>
            </div>
            <div>
                <dt class="text-sm">{{ $payment->method->label() }}</dt>
                <dd class="font-semibold">{{ $payment->status->label() }}</dd>
            </div>
            @if ($payment->paid_at !== null)
                @if ($payment->collection_method !== null)
                    <div><dt class="text-sm">Hình thức thực thu</dt><dd>{{ $payment->collection_method->label() }}</dd></div>
                @endif
                <div>
                    <dt class="text-sm">Xác nhận thanh toán lúc</dt>
                    <dd><x-customer.order-time :date="$payment->paid_at" /></dd>
                </div>
            @endif
            @if ($payment->collection_reference !== null)
                <div class="wrap-anywhere">
                    <dt class="text-sm">Mã giao dịch thu</dt>
                    <dd>{{ $payment->collection_reference }}</dd>
                </div>
            @endif
            @if ($payment->refunded_at !== null)
                <div>
                    <dt class="text-sm">Ghi nhận hoàn tiền lúc</dt>
                    <dd><x-customer.order-time :date="$payment->refunded_at" /></dd>
                </div>
            @endif
            @if ($payment->refund_reference !== null)
                <div class="wrap-anywhere">
                    <dt class="text-sm">Tham chiếu hoàn tiền</dt>
                    <dd>{{ $payment->refund_reference }}</dd>
                </div>
            @endif
        </dl>

        @if($payment->status === \App\Enums\PaymentStatus::Pending && $payment->transfer_details)
            <x-payment-qr :amount="$payment->amount_vnd" :memo="$payment->order->order_number" :bank="$payment->transfer_details" />
        @endif

        @if ($canReconcileDemo)
            <form method="POST" action="{{ route('operations.payments.demo-reconcile', $payment->order->order_number) }}" class="space-y-4 rounded-xl border border-terrasse-ink/15 bg-terrasse-paper p-4">
                @csrf
                <h2 class="text-lg font-semibold">Đối chiếu cổng mô phỏng</h2>
                <p class="text-sm">Tra cứu giao dịch đã ghi tại cổng khi cửa hàng chưa nhận được callback.</p>
                <flux:button type="submit" variant="primary">Đối chiếu giao dịch mô phỏng</flux:button>
            </form>
        @elseif ($canReconcile)
            <form method="POST" action="{{ route('operations.payments.reconcile', $payment->order->order_number) }}" class="space-y-4 rounded-xl border border-terrasse-ink/15 bg-terrasse-paper p-4">
                @csrf
                <input type="hidden" name="method" value="{{ $payment->method->value }}">
                <input type="hidden" name="expected_status" value="{{ $payment->status->value }}">
                <h2 class="text-lg font-semibold">Xác nhận chuyển khoản</h2>
                @if ($payment->amount_vnd === 0)
                    <input type="hidden" name="amount_vnd" value="0">
                    <p class="text-sm">Tổng tiền bằng 0 ₫. Xác nhận đơn không phát sinh khoản thu.</p>
                    <flux:error name="amount_vnd" />
                    <flux:error name="collection_reference" />
                @else
                    <p class="text-sm">Đối chiếu số tiền và mã giao dịch trên sao kê trước khi xác nhận.</p>
                    <div class="space-y-2">
                        <label class="block text-sm font-medium" for="transfer-amount">Số tiền đã thu (VND)</label>
                        <flux:input id="transfer-amount" name="amount_vnd" inputmode="numeric" pattern="[0-9]+" :value="is_scalar(old('amount_vnd')) ? old('amount_vnd') : ''" required />
                        <flux:error name="amount_vnd" />
                    </div>
                    <div class="space-y-2">
                        <label class="block text-sm font-medium" for="collection-reference">Mã giao dịch trên sao kê</label>
                        <flux:input id="collection-reference" name="collection_reference" maxlength="255" :value="is_string(old('collection_reference')) ? old('collection_reference') : ''" required />
                        <flux:error name="collection_reference" />
                    </div>
                @endif
                <div class="space-y-2">
                    <label class="block text-sm font-medium" for="transfer-note">Ghi chú nội bộ (không bắt buộc)</label>
                    <flux:textarea id="transfer-note" name="note" maxlength="500" rows="2">{{ is_string(old('note')) ? old('note') : '' }}</flux:textarea>
                    <flux:error name="note" />
                </div>
                <flux:button type="submit" variant="primary">Xác nhận thanh toán</flux:button>
            </form>
        @elseif ($payment->status === \App\Enums\PaymentStatus::RefundPending)
            @can('access-admin')
                <form method="POST" action="{{ route('operations.payments.refund', $payment->order->order_number) }}" class="space-y-4 rounded-xl border border-terrasse-ink/15 bg-terrasse-paper p-4">
                    @csrf
                    <input type="hidden" name="method" value="{{ $payment->method->value }}">
                    <input type="hidden" name="expected_status" value="{{ $payment->status->value }}">
                    <h2 class="text-lg font-semibold">Ghi nhận hoàn tiền</h2>
                    @if ($payment->method === \App\Enums\PaymentMethod::DemoGateway)
                        <p class="text-sm">Nhập tham chiếu để ghi nhận hoàn tiền mô phỏng.</p>
                    @else
                        <p class="text-sm">Hoàn tiền ngoài hệ thống trước, sau đó nhập tham chiếu giao dịch đã thực hiện.</p>
                    @endif
                    <div class="space-y-2">
                        <label class="block text-sm font-medium" for="refund-reference">Tham chiếu hoàn tiền</label>
                        <flux:input id="refund-reference" name="refund_reference" maxlength="255" :value="is_string(old('refund_reference')) ? old('refund_reference') : ''" required />
                        <flux:error name="refund_reference" />
                    </div>
                    <div class="space-y-2">
                        <label class="block text-sm font-medium" for="refund-note">Ghi chú nội bộ (không bắt buộc)</label>
                        <flux:textarea id="refund-note" name="note" maxlength="500" rows="2">{{ is_string(old('note')) ? old('note') : '' }}</flux:textarea>
                        <flux:error name="note" />
                    </div>
                    <flux:button type="submit" variant="primary">Ghi nhận đã hoàn tiền</flux:button>
                </form>
            @else
                <p class="rounded-xl border border-terrasse-ink/15 bg-terrasse-paper p-4 text-sm">Quản trị viên ghi nhận hoàn tiền cho khoản này sau khi hoàn ngoài hệ thống.</p>
            @endcan
        @elseif ($payment->method === \App\Enums\PaymentMethod::Cod && $payment->status === \App\Enums\PaymentStatus::Pending)
            <p class="rounded-xl border border-terrasse-ink/15 bg-terrasse-paper p-4 text-sm">Ghi nhận tiền thu khi bàn giao và hoàn tất đơn.</p>
        @endif
    </section>
</x-layouts::admin>
