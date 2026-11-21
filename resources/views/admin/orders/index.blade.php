@use('App\Enums\OrderStatus')
@use('App\Enums\PaymentMethod')

<x-layouts::admin title="Quản lý đơn hàng">
    <section class="space-y-6">
        <header class="space-y-2 border-b border-terrasse-ink/15 pb-5">
            <h1 class="font-semibold">Quản lý đơn hàng</h1>
            <p class="terrasse-muted text-sm">Tra cứu đơn, cách nhận và tình trạng thanh toán.</p>
        </header>

        <form method="GET" action="{{ route('admin.orders.index') }}" class="terrasse-panel space-y-4 rounded-md p-4">
            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
                <div class="min-w-0 space-y-2">
                    <label class="block text-sm font-medium" for="order-number">Mã đơn</label>
                    <flux:input id="order-number" name="number" :value="$filters['number'] ?? ''" placeholder="Mã hoặc đầu mã" maxlength="32" />
                    <flux:error name="number" />
                </div>
                <div class="min-w-0 space-y-2">
                    <label class="block text-sm font-medium" for="from">Đặt từ ngày</label>
                    <flux:input id="from" name="from" type="date" :value="$filters['from'] ?? ''" />
                    <flux:error name="from" />
                </div>
                <div class="min-w-0 space-y-2">
                    <label class="block text-sm font-medium" for="to">Đặt đến ngày</label>
                    <flux:input id="to" name="to" type="date" :value="$filters['to'] ?? ''" />
                    <flux:error name="to" />
                </div>
                <div class="min-w-0 space-y-2">
                    <label class="block text-sm font-medium" for="status">Trạng thái đơn</label>
                    <flux:select id="status" name="status">
                        <flux:select.option value="">Tất cả trạng thái</flux:select.option>
                        @foreach (OrderStatus::cases() as $option)
                            <flux:select.option :value="$option->value" :selected="($filters['status'] ?? '') === $option->value">{{ $option->label() }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:error name="status" />
                </div>
                <div class="min-w-0 space-y-2">
                    <label class="block text-sm font-medium" for="method">Phương thức thanh toán</label>
                    <flux:select id="method" name="method">
                        <flux:select.option value="">Tất cả phương thức</flux:select.option>
                        @foreach (PaymentMethod::cases() as $option)
                            <flux:select.option :value="$option->value" :selected="($filters['method'] ?? '') === $option->value">{{ $option->label() }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:error name="method" />
                </div>
            </div>
            <div class="flex flex-wrap items-center gap-4">
                <flux:button type="submit" variant="primary">Lọc đơn hàng</flux:button>
                @if ($filters !== [])
                    <flux:link :href="route('admin.orders.index')">Xóa bộ lọc</flux:link>
                @endif
            </div>
        </form>

        @if ($orders->isEmpty())
            <p class="rounded-xl border border-terrasse-ink/15 bg-terrasse-paper p-5">
                {{ $orders->total() > 0 ? 'Trang này không có đơn hàng.' : 'Không tìm thấy đơn hàng.' }}
            </p>
        @else
            <div class="terrasse-panel max-w-full overflow-x-auto rounded-md" role="region" aria-label="Danh sách đơn hàng" tabindex="0">
                <table class="w-full text-left text-sm">
                    <thead class="bg-terrasse-ink/5 whitespace-nowrap">
                        <tr>
                            <th scope="col" class="px-4 py-3">Mã đơn</th>
                            <th scope="col" class="px-4 py-3">Giờ đặt</th>
                            <th scope="col" class="px-4 py-3">Cách nhận</th>
                            <th scope="col" class="px-4 py-3">Trạng thái đơn</th>
                            <th scope="col" class="px-4 py-3">Thanh toán</th>
                            <th scope="col" class="px-4 py-3 text-right">Tổng tiền</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-terrasse-ink/15">
                        @foreach ($orders as $order)
                            <tr>
                                <th scope="row" class="px-4 py-3 whitespace-nowrap">
                                    <flux:link :href="route('admin.orders.show', $order->order_number)">{{ $order->order_number }}</flux:link>
                                </th>
                                <td class="px-4 py-3 whitespace-nowrap"><x-customer.order-time :date="$order->created_at" /></td>
                                <td class="min-w-36 px-4 py-3 wrap-anywhere">
                                    {{ $order->fulfillment_type->label() }}
                                    <span class="terrasse-muted block text-xs">{{ $order->recipient_name }}</span>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap"><x-terrasse.status :status="$order->status" /></td>
                                <td class="min-w-36 px-4 py-3">
                                    @if ($order->payment)
                                        <x-terrasse.status :status="$order->payment->status" />
                                        <span class="terrasse-muted block text-xs">{{ $order->payment->method->label() }}</span>
                                    @else
                                        <span class="terrasse-muted text-xs">Chưa có thông tin</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-right font-semibold whitespace-nowrap tabular-nums"><x-customer.vnd :amount="$order->total_vnd" /></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        {{ $orders->links() }}
    </section>
</x-layouts::admin>
