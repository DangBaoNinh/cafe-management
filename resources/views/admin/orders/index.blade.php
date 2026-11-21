@use('App\Enums\OrderStatus')
@use('App\Enums\PaymentMethod')

<x-layouts::admin title="Quản lý đơn hàng">
    <section class="space-y-6">
        <h1 class="text-2xl font-semibold sm:text-3xl">Quản lý đơn hàng</h1>

        <form method="GET" action="{{ route('admin.orders.index') }}" class="space-y-4 rounded-xl border border-coffee/15 bg-white p-4">
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
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
            <p class="rounded-xl border border-coffee/15 bg-white p-5">
                {{ $orders->total() > 0 ? 'Trang này không có đơn hàng.' : 'Không tìm thấy đơn hàng.' }}
            </p>
        @else
            <ul class="divide-y divide-coffee/15 rounded-xl border border-coffee/15 bg-white">
                @foreach ($orders as $order)
                    <li class="space-y-3 p-4 sm:p-5">
                        <div class="flex flex-wrap items-baseline justify-between gap-2">
                            <h2 class="min-w-0 font-semibold wrap-anywhere">
                                <flux:link :href="route('admin.orders.show', $order->order_number)">{{ $order->order_number }}</flux:link>
                            </h2>
                            <strong><x-customer.vnd :amount="$order->total_vnd" /></strong>
                        </div>
                        <dl class="grid gap-2 text-sm sm:grid-cols-2 lg:grid-cols-4">
                            <div>
                                <dt class="font-medium">Giờ đặt</dt>
                                <dd><x-customer.order-time :date="$order->created_at" /></dd>
                            </div>
                            <div class="wrap-anywhere">
                                <dt class="font-medium">{{ $order->fulfillment_type->label() }}</dt>
                                <dd>{{ $order->recipient_name }}</dd>
                            </div>
                            <div>
                                <dt class="font-medium">Trạng thái đơn</dt>
                                <dd>{{ $order->status->label() }}</dd>
                            </div>
                            <div>
                                <dt class="font-medium">{{ $order->payment?->method->label() ?? 'Thanh toán' }}</dt>
                                <dd>{{ $order->payment?->status->label() ?? 'Chưa có thông tin' }}</dd>
                            </div>
                        </dl>
                    </li>
                @endforeach
            </ul>
        @endif

        {{ $orders->links() }}
    </section>
</x-layouts::admin>
