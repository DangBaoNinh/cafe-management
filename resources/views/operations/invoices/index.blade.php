<x-layouts::admin :title="$history ? 'Lịch sử hóa đơn' : 'Hóa đơn thanh toán'">
    <section class="space-y-6">
        <header class="space-y-2">
            <h1>{{ $history ? 'Lịch sử hóa đơn' : 'Hóa đơn thanh toán' }}</h1>
            <p class="terrasse-muted">{{ $history ? 'Các hóa đơn đã ghi nhận thu tiền, gồm cả khoản đang chờ hoàn hoặc đã hoàn.' : 'Đơn hàng chờ thu tiền và hóa đơn bàn/phòng đã chốt, chưa thanh toán.' }}</p>
        </header>
        <nav class="flex flex-wrap gap-3" aria-label="Hóa đơn">
            @can('access-admin')<a class="pill" href="{{ route('admin.invoices.index') }}">Quản lý hóa đơn / thay đổi trạng thái</a>@endcan
            <a class="pill {{ ! $history ? 'active' : '' }}" href="{{ route('operations.invoices.index') }}" @if(!$history) aria-current="page" @endif>Chờ thanh toán</a>
            <a class="pill {{ $history ? 'active' : '' }}" href="{{ route('operations.invoices.history') }}" @if($history) aria-current="page" @endif>Lịch sử hóa đơn</a>
        </nav>
        <form method="GET" action="{{ route($history ? 'operations.invoices.history' : 'operations.invoices.index') }}" class="terrasse-panel grid gap-4 p-4 sm:grid-cols-2 xl:grid-cols-4">
            <flux:input name="from" label="Từ ngày" type="date" :value="is_string(old('from')) ? old('from') : $from?->toDateString()" />
            <flux:input name="to" label="Đến ngày" type="date" :value="is_string(old('to')) ? old('to') : $to?->toDateString()" />
            <flux:select name="source" label="Loại hóa đơn">
                @foreach(['all' => 'Tất cả', 'order' => 'Đơn giao / nhận tại quán', 'table' => 'Bàn / phòng'] as $value => $label)
                    <flux:select.option :value="$value" :selected="$source === $value">{{ $label }}</flux:select.option>
                @endforeach
            </flux:select>
            <div class="flex flex-wrap items-end gap-3"><flux:button type="submit" variant="primary">Lọc hóa đơn</flux:button><flux:link :href="route($history ? 'operations.invoices.history' : 'operations.invoices.index')">Đặt lại</flux:link></div>
            <p class="terrasse-muted text-sm sm:col-span-full">{{ $history ? 'Lọc theo ngày thanh toán' : 'Lọc theo ngày lập hóa đơn' }} theo giờ Việt Nam, tính cả ngày kết thúc. Tối đa 366 ngày mỗi lần. {{ $history ? 'Mặc định 7 ngày gần nhất.' : 'Bỏ trống ngày để xem tất cả.' }}</p>
            @if($errors->any())<div role="alert" class="text-terrasse-danger sm:col-span-full">@foreach($errors->all() as $message)<p>{{ $message }}</p>@endforeach</div>@endif
        </form>
        <p class="text-sm">{{ number_format($bills->total(), 0, ',', '.') }} hóa đơn</p>
        <div class="terrasse-panel overflow-x-auto" role="region" aria-label="Danh sách hóa đơn" tabindex="0">
            <table class="w-full whitespace-nowrap text-left text-sm">
                <thead><tr><th class="p-4">Hóa đơn</th><th class="p-4">Khách / Chỗ</th><th class="p-4">{{ $history ? 'Thanh toán lúc' : 'Lập lúc' }}</th><th class="p-4">Giá trị hóa đơn</th><th class="p-4">Thanh toán</th><th class="p-4">Thao tác</th></tr></thead>
                <tbody class="divide-y divide-terrasse-ink/15">
                    @forelse($bills as $bill)
                        @php($url = match($bill->source) { 'order' => route('operations.invoices.orders.show', $bill->reference), 'prepaid' => auth()->user()->can('access-admin') ? route('admin.orders.show', $bill->display_number) : route('operations.invoices.prepaid.show', $bill->reference), default => route('operations.invoices.tables.show', $bill->reference) })
                        <tr>
                            <td class="p-4"><a href="{{ $url }}" class="underline underline-offset-4">{{ $bill->display_number ?? 'BH-'.$bill->id }}</a><p class="terrasse-muted mt-1">{{ $bill->source === 'order' ? 'Đơn hàng' : 'Bàn / phòng' }}</p></td>
                            <td class="p-4">{{ $bill->label }}</td>
                            <td class="p-4"><x-customer.order-time :date="\Carbon\CarbonImmutable::parse($bill->event_at, 'UTC')" /></td>
                            <td class="p-4"><x-customer.vnd :amount="(int) $bill->amount_vnd" /></td>
                            <td class="p-4">{{ \App\Enums\PaymentStatus::tryFrom($bill->payment_status)?->label() ?? 'Chưa xác định' }}<p class="terrasse-muted mt-1">{{ $bill->method === 'counter' ? 'Tại quầy' : \App\Enums\PaymentMethod::from($bill->method)->label() }}</p>@if($bill->collection_method)<p class="terrasse-muted mt-1">Thực thu: {{ \App\Enums\CollectionMethod::from($bill->collection_method)->label() }}</p>@endif</td>
                            <td class="p-4">
                                <div class="flex flex-wrap gap-2">
                                    <flux:button size="sm" :href="$url">Xem hóa đơn</flux:button>
                                    @can('access-admin')
                                        @if(in_array($bill->source, ['order', 'prepaid'], true))
                                            <flux:button size="sm" :href="route('admin.invoices.show', $bill->display_number)">{{ ($bill->source === 'prepaid' || $bill->payment_status === 'pending') ? 'Thay đổi trạng thái' : 'Xem trạng thái' }}</flux:button>
                                        @endif
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="p-6">Không có hóa đơn phù hợp.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{ $bills->onEachSide(1)->links() }}
    </section>
</x-layouts::admin>
