@php
    $invoiceManagement = request()->routeIs('admin.invoices.*');
    $listingRoute = $invoiceManagement ? 'admin.invoices.index' : 'admin.orders.index';
    $detailRoute = $invoiceManagement ? 'admin.invoices.show' : 'admin.orders.show';
@endphp
<x-layouts::admin :title="$invoiceManagement ? 'Quản lý hóa đơn' : 'Quản lý đơn hàng'">
    @php
        $selectFilters = [
            'source' => ['Nguồn đơn', ['online' => 'Trực tuyến', 'counter' => 'Tại quầy', 'legacy' => 'Hóa đơn bàn cũ']],
            'display_status' => ['Trạng thái', ['paid' => 'Đã thanh toán', 'cancelled' => 'Đã hủy', 'pending' => 'Chờ thanh toán']],
            'status' => ['Tiến trình đơn', [...collect(App\Enums\OrderStatus::cases())->mapWithKeys(fn ($status) => [$status->value => $status->label()])->all(), 'pending_payment' => 'Chờ thanh toán tại quầy']],
            'method' => ['Phương thức thanh toán', ['cash' => 'Tiền mặt', 'bank_transfer' => 'Chuyển khoản', 'cod' => 'Thanh toán khi nhận', 'demo_gateway' => 'Cổng thanh toán mô phỏng', 'counter' => 'Tại quầy (hóa đơn cũ)']],
        ];
    @endphp
    <section class="space-y-6">
        <header class="space-y-2 border-b border-terrasse-ink/15 pb-5">
            <h1 class="font-semibold">{{ $invoiceManagement ? 'Quản lý hóa đơn' : 'Quản lý đơn hàng' }}</h1>
            <p>Đơn trực tuyến và hóa đơn tại quầy được cập nhật từ cùng dữ liệu vận hành.</p>
        </header>

        @if($invoiceManagement)
            @if($pendingDeposits > 0)<p role="status" class="terrasse-panel rounded p-4"><strong>{{ $pendingDeposits }} chứng từ đặt cọc đã nhận đang chờ trừ vào hóa đơn.</strong> <a class="underline" href="{{ route('admin.invoices.index', ['source' => 'deposit']) }}">Xem tiền cọc</a></p>@endif
            <section class="terrasse-panel space-y-3 rounded p-4" aria-labelledby="invoice-status-help">
                <h2 id="invoice-status-help" class="text-lg font-semibold">Thay đổi trạng thái hóa đơn</h2>
                <p>Ở cột Thao tác, chọn Thay đổi trạng thái để mở hóa đơn. Chọn trạng thái mới và xác minh trước khi cập nhật.</p>
                <p class="text-sm">Admin có thể đổi Hoàn tất/Đã hủy trên đơn đã thanh toán. Khoản đã thu và chứng từ được giữ nguyên; hoàn tiền xử lý riêng.</p>
                <flux:link :href="route('admin.invoices.index', ['display_status' => 'pending'])">Xem hóa đơn chờ thanh toán</flux:link>
            </section>
        @endif
        <form method="GET" action="{{ route($listingRoute) }}" class="terrasse-panel space-y-4 rounded p-4">
            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <label>Mã đơn
                    <flux:input name="number" :value="$filters['number'] ?? ''" placeholder="Mã hoặc đầu mã" maxlength="36" />
                </label>
                <label>Đặt từ ngày
                    <flux:input name="from" type="date" :value="$filters['from'] ?? ''" />
                </label>
                <label>Đặt đến ngày
                    <flux:input name="to" type="date" :value="$filters['to'] ?? ''" />
                </label>
                @foreach($selectFilters as $name => [$label, $options])
                    <label>{{ $label }}
                        <select name="{{ $name }}" class="mt-2 w-full rounded border p-2">
                            <option value="">Tất cả</option>
                            @foreach($options as $value => $text)
                                <option value="{{ $value }}" @selected(($filters[$name] ?? '') === $value)>{{ $text }}</option>
                            @endforeach
                        </select>
                    </label>
                @endforeach
            </div>
            @if($errors->any())
                <ul role="alert">
                    @foreach($errors->all() as $message)<li>{{ $message }}</li>@endforeach
                </ul>
            @endif
            <div class="flex gap-4">
                <flux:button type="submit" variant="primary">Lọc đơn hàng</flux:button>
                <flux:link :href="route($listingRoute)">Xóa bộ lọc</flux:link>
            </div>
        </form>

        <div class="terrasse-panel max-w-full overflow-x-auto rounded" role="region" aria-label="Danh sách đơn hàng" tabindex="0">
            <table class="w-full text-left text-sm whitespace-nowrap">
                <thead>
                    <tr>
                        @foreach(['Mã đơn', 'Giờ đặt', 'Nguồn / cách nhận', 'Trạng thái', 'Tiến trình đơn', 'Phương thức', 'Tổng tiền', 'Thao tác'] as $label)
                            <th scope="col" class="px-4 py-3">{{ $label }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody class="divide-y divide-terrasse-ink/15">
                    @forelse($orders as $order)
                        @php($detailUrl = match($order->source) { 'legacy' => route('operations.invoices.tables.show', $order->number), 'deposit' => route('operations.deposits.show', $order->document_reference), default => route($detailRoute, $order->number) })
                        <tr>
                            <th scope="row" class="px-4 py-3">
                                <flux:link :href="$detailUrl">{{ $order->number }}</flux:link>
                            </th>
                            <td class="px-4 py-3"><x-customer.order-time :date="Carbon\CarbonImmutable::parse($order->created_at, 'UTC')" /></td>
                            <td class="px-4 py-3">
                                {{ $order->source === 'deposit' ? 'Đặt cọc bàn' : ($order->source === 'online' ? App\Enums\FulfillmentType::from($order->fulfillment)->label() : 'Tại quầy / bàn') }}
                                <span class="block text-xs">{{ $order->label }}</span>
                            </td>
                            <td class="px-4 py-3"><x-admin.order-state :state="$order->display_status" />@if($order->source === 'counter' && $order->payment_status === 'paid' && $order->status === 'cancelled')<span class="mt-1 block text-xs">Đã thu tiền · hoàn tiền riêng</span>@endif</td>
                            <td class="px-4 py-3">{{ $order->source === 'deposit' ? (new App\Models\ReservationDeposit(['status' => $order->status]))->statusLabel() : ($order->source === 'online' ? App\Enums\OrderStatus::from($order->status)->label() : App\Enums\CounterOrderStatus::from($order->status)->label()) }}</td>
                            <td class="px-4 py-3">{{ match($order->collection_method ?? $order->method) { 'cash' => 'Tiền mặt', 'bank_transfer' => 'Chuyển khoản', 'cod' => 'Thanh toán khi nhận', 'demo_gateway' => 'Cổng thanh toán mô phỏng', default => 'Chưa phân loại (hóa đơn cũ)' } }}</td>
                            <td class="px-4 py-3 text-right font-semibold"><x-customer.vnd :amount="(int) $order->total_vnd" /></td>
                            <td class="px-4 py-3">
                                <flux:button size="sm" :href="$detailUrl">{{ $order->source === 'deposit' ? 'Xem chứng từ cọc' : ((($order->source === 'counter' && $order->payment_status !== 'voided') || ($order->display_status === 'pending' && $order->source === 'online')) ? ($invoiceManagement ? 'Thay đổi trạng thái' : 'Cập nhật trạng thái') : 'Xem chi tiết') }}</flux:button>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="p-5">Không tìm thấy đơn hàng.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{ $orders->links() }}
    </section>
</x-layouts::admin>
