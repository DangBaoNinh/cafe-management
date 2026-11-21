<x-layouts::admin title="Hồ sơ khách hàng">
    <section class="space-y-6">
        <flux:link :href="route('admin.customers.index')">Về danh sách khách hàng</flux:link>

        <div class="space-y-4 rounded-xl border border-coffee/15 bg-white p-4 sm:p-5">
            <h1 class="text-2xl font-semibold wrap-anywhere sm:text-3xl">{{ $customer->name }}</h1>
            <dl class="grid gap-4 text-sm sm:grid-cols-2">
                <div class="wrap-anywhere">
                    <dt class="font-medium">Email</dt>
                    <dd>{{ $customer->email }}</dd>
                </div>
                <div>
                    <dt class="font-medium">Trạng thái tài khoản</dt>
                    <dd>{{ $customer->is_active ? 'Đang hoạt động' : 'Đã khóa' }}</dd>
                </div>
            </dl>
            <flux:link :href="route('admin.accounts.edit', $customer->id)">Chỉnh sửa tài khoản</flux:link>
        </div>

        <section aria-labelledby="customer-orders" class="space-y-4">
            <h2 id="customer-orders" class="text-xl font-semibold">Đơn hàng ({{ $orders->total() }})</h2>

            @if ($orders->isEmpty())
                <p class="rounded-xl border border-coffee/15 bg-white p-5">
                    {{ $orders->total() > 0 ? 'Trang này không có đơn hàng.' : 'Khách hàng chưa có đơn hàng.' }}
                </p>
            @else
                <ul class="divide-y divide-coffee/15 rounded-xl border border-coffee/15 bg-white">
                    @foreach ($orders as $order)
                        <li class="grid gap-2 p-4 sm:grid-cols-[minmax(0,1fr)_auto] sm:items-center sm:p-5">
                            <div class="wrap-anywhere">
                                <h3 class="font-semibold">
                                    <flux:link :href="route('admin.orders.show', $order->order_number)">{{ $order->order_number }}</flux:link>
                                </h3>
                                <p class="text-sm"><x-customer.order-time :date="$order->created_at" /></p>
                            </div>
                            <div class="text-sm sm:text-right">
                                <p>{{ $order->status->label() }}</p>
                                <strong><x-customer.vnd :amount="$order->total_vnd" /></strong>
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif

            {{ $orders->links() }}
        </section>
    </section>
</x-layouts::admin>
