@use('App\Enums\OrderStatus')

<x-layouts::admin title="Tổng quan quản trị">
    @php
        $placedOrders = array_sum($statuses) + $counterStatuses->sum();
        $peakRevenue = max(1, max(array_column($daily, 'total')));
    @endphp
    <div class="space-y-8">
        <header class="space-y-2 border-b border-terrasse-ink/15 pb-5">
            <h1 class="text-2xl font-semibold sm:text-3xl">Tổng quan quản trị</h1>
            <p class="terrasse-muted text-sm">Doanh thu và tình trạng đơn hàng của quán.</p>
        </header>

        <form method="GET" action="{{ route('admin.dashboard') }}" class="terrasse-panel grid items-start gap-4 rounded-md p-4 sm:grid-cols-2 xl:grid-cols-[1fr_1fr_auto]">
            <div class="min-w-0 space-y-2">
                <label class="block text-sm font-medium" for="revenue-from">Từ ngày</label>
                <flux:input id="revenue-from" name="from" type="date" :value="is_string(old('from')) ? old('from') : $from->toDateString()" required />
                <flux:error name="from" />
            </div>
            <div class="min-w-0 space-y-2">
                <label class="block text-sm font-medium" for="revenue-to">Đến ngày</label>
                <flux:input id="revenue-to" name="to" type="date" :value="is_string(old('to')) ? old('to') : $to->toDateString()" required />
                <flux:error name="to" />
            </div>
            <div class="flex flex-wrap items-center gap-4 sm:col-span-2 xl:col-span-1 xl:mt-7">
                <flux:button type="submit" variant="primary">Xem báo cáo</flux:button>
                <flux:link :href="route('admin.dashboard')">7 ngày gần nhất</flux:link>
            </div>
            <p class="terrasse-muted text-xs sm:col-span-full">Khoảng ngày theo giờ Việt Nam (UTC+7), tối đa {{ $maxDays }} ngày.</p>
        </form>

        <section aria-labelledby="revenue-heading" class="space-y-4">
            <header class="space-y-1">
                <h2 id="revenue-heading" class="terrasse-display text-3xl font-semibold">Doanh thu</h2>
                <p class="terrasse-muted text-sm">{{ $from->format('d/m/Y') }} – {{ $to->format('d/m/Y') }} · Theo ngày hoàn tất của đơn đã thanh toán. Đơn tại quầy và hóa đơn bàn cũ tính theo ngày thu tiền.</p>
            </header>
            <dl class="grid grid-cols-2 gap-3 xl:grid-cols-5">
                <div class="terrasse-revenue-total col-span-2 rounded-md p-4 xl:col-span-1">
                    <dt class="text-sm">Tổng thu</dt>
                    <dd class="terrasse-display mt-2 text-3xl font-semibold tabular-nums wrap-anywhere"><x-customer.vnd :amount="$totals['total']" /></dd>
                </div>
                <div class="terrasse-panel rounded-md p-4">
                    <dt class="text-sm">Hóa đơn đủ điều kiện đã thu</dt>
                    <dd class="terrasse-display mt-2 text-3xl font-semibold tabular-nums">{{ number_format($totals['orders'], 0, ',', '.') }}</dd>
                </div>
                <div class="terrasse-panel rounded-md p-4">
                    <dt class="text-sm">Tạm tính</dt>
                    <dd class="terrasse-display mt-2 text-3xl font-semibold tabular-nums wrap-anywhere"><x-customer.vnd :amount="$totals['subtotal']" /></dd>
                </div>
                <div class="terrasse-panel rounded-md p-4">
                    <dt class="text-sm">Giảm giá</dt>
                    <dd class="terrasse-display mt-2 text-3xl font-semibold tabular-nums wrap-anywhere"><x-customer.vnd :amount="$totals['discount']" /></dd>
                </div>
                <div class="terrasse-panel rounded-md p-4">
                    <dt class="text-sm">Phí giao</dt>
                    <dd class="terrasse-display mt-2 text-3xl font-semibold tabular-nums wrap-anywhere"><x-customer.vnd :amount="$totals['delivery']" /></dd>
                </div>
            </dl>
        </section>

        <section class="terrasse-panel space-y-4 rounded p-5" aria-labelledby="payment-channels-heading">
            <h2 id="payment-channels-heading" class="text-xl font-semibold">Doanh thu theo phương thức thanh toán</h2>
            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                @foreach(['cash' => 'Tiền mặt', 'bank_transfer' => 'Chuyển khoản', 'demo_gateway' => 'Cổng mô phỏng', 'unclassified' => 'Chưa phân loại'] as $key => $label)
                    <div class="rounded border border-terrasse-ink/15 p-4"><h3 class="font-semibold">{{ $label }}</h3><p class="mt-2 text-xl font-bold"><x-customer.vnd :amount="$channels[$key]['total']" /></p><p class="text-sm">{{ $channels[$key]['orders'] }} hóa đơn · {{ $totals['total'] > 0 ? round($channels[$key]['total'] * 100 / $totals['total'], 1) : 0 }}%</p></div>
                @endforeach
            </div>
            <div class="revenue-channel-track" role="img" aria-label="Tỷ trọng doanh thu tiền mặt, chuyển khoản và phương thức khác">
                @foreach(['cash' => 'revenue-cash', 'bank_transfer' => 'revenue-transfer', 'demo_gateway' => 'revenue-other', 'unclassified' => 'revenue-other'] as $key => $class)
                    <span class="{{ $class }}" style="width: {{ $totals['total'] > 0 ? $channels[$key]['total'] * 100 / $totals['total'] : 0 }}%"></span>
                @endforeach
            </div>
            <p class="text-sm">Xanh lá: tiền mặt · Xanh dương: chuyển khoản · Xám: phương thức khác. Hóa đơn cũ chưa lưu cách thu tiền được để Chưa phân loại.</p>
            <p class="text-sm">Phụ phí đã gộp trong tổng thu: <strong><x-customer.vnd :amount="$feesTotal" /></strong>. Hóa đơn chưa thanh toán hoặc đã hủy không tính vào tổng doanh thu, biểu đồ và thống kê phương thức thanh toán.</p>
        </section>

        <section class="flex flex-wrap gap-3" aria-label="Tình trạng thanh toán đơn trong khoảng ngày đặt">
            @foreach(['paid', 'cancelled', 'pending'] as $state)<div class="terrasse-panel rounded p-3"><x-admin.order-state :$state /> <strong>{{ $displayStatuses->get($state, 0) }}</strong></div>@endforeach
        </section>

        <div class="grid gap-4 xl:grid-cols-[minmax(0,1.6fr)_minmax(0,1fr)]">
            <section aria-labelledby="revenue-chart-heading" class="terrasse-panel min-w-0 space-y-3 rounded-md p-4 sm:p-5">
                <h2 id="revenue-chart-heading" class="terrasse-display text-3xl font-semibold">Biểu đồ doanh thu</h2>
                @if ($totals['orders'] === 0)
                    <p class="terrasse-muted text-sm">Chưa có đơn hoàn tất đã thanh toán trong khoảng này.</p>
                @else
                    <div role="region" aria-labelledby="revenue-chart-heading" tabindex="0" class="overflow-x-auto pb-2">
                        <ul class="terrasse-report-chart">
                            @foreach ($daily as $date => $day)
                                <li title="{{ $date }} · {{ number_format($day['total'], 0, ',', '.') }} ₫">
                                    <span class="terrasse-report-bar revenue-stacked-bar" style="--bar-height: {{ round($day['total'] * 100 / $peakRevenue, 2) }}%" aria-hidden="true">
                                        @foreach(['cash' => 'revenue-cash', 'bank_transfer' => 'revenue-transfer', 'other' => 'revenue-other'] as $key => $class)<span class="{{ $class }}" style="height: {{ $day['total'] > 0 ? $dailyChannels[$date][$key] * 100 / $day['total'] : 0 }}%"></span>@endforeach
                                    </span>
                                    <time datetime="{{ $date }}" class="terrasse-muted border-t border-terrasse-ink/15 pt-1 text-xs">{{ Illuminate\Support\Facades\Date::parse($date)->format('d/m') }}</time>
                                    <span class="sr-only"><x-customer.vnd :amount="$day['total']" /></span>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            </section>

            <section aria-labelledby="status-heading" class="terrasse-panel space-y-4 rounded-md p-4 sm:p-5">
                <header class="space-y-1">
                    <h2 id="status-heading" class="terrasse-display text-3xl font-semibold">Trạng thái đơn theo ngày đặt</h2>
                    <p class="terrasse-muted text-sm">Tổng số đơn đặt trong khoảng đã chọn: {{ number_format($placedOrders, 0, ',', '.') }}.</p>
                </header>
                <p class="text-sm font-semibold">Đơn trực tuyến</p>
                <dl class="grid grid-cols-1 gap-x-4 gap-y-2 sm:grid-cols-2">
                    @foreach (OrderStatus::cases() as $status)
                        <div class="flex items-center justify-between gap-2 border-b border-terrasse-ink/10 pb-2">
                            <dt><x-terrasse.status :$status /></dt>
                            <dd class="text-sm font-semibold tabular-nums">{{ number_format($statuses[$status->value], 0, ',', '.') }}</dd>
                        </div>
                    @endforeach
                </dl>
                <p class="text-sm font-semibold">Đơn tại quầy / hóa đơn bàn cũ</p>
                <dl class="space-y-2">
                    @foreach(App\Enums\CounterOrderStatus::cases() as $state)
                        <div class="flex justify-between gap-2 border-b border-terrasse-ink/10 pb-2"><dt>{{ $state->label() }}</dt><dd class="font-semibold">{{ $counterStatuses->get($state->value, 0) }}</dd></div>
                    @endforeach
                </dl>
            </section>
        </div>

        <section class="space-y-4">
            <h2 id="daily-revenue-heading" class="terrasse-display text-3xl font-semibold">Doanh thu theo ngày</h2>
            <div role="region" aria-labelledby="daily-revenue-heading" tabindex="0" class="terrasse-panel max-w-full overflow-x-auto rounded-md focus-visible:outline-2 focus-visible:outline-accent">
                <table class="w-full text-sm whitespace-nowrap">
                    <thead class="border-b border-terrasse-ink/15 bg-terrasse-ink/5">
                        <tr class="text-right">
                            <th scope="col" class="px-4 py-3 text-left">Ngày hoàn tất</th>
                            <th scope="col" class="px-4 py-3">Số đơn</th>
                            <th scope="col" class="px-4 py-3">Tạm tính</th>
                            <th scope="col" class="px-4 py-3">Giảm giá</th>
                            <th scope="col" class="px-4 py-3">Phí giao</th>
                            <th scope="col" class="px-4 py-3">Phụ phí</th><th scope="col" class="px-4 py-3">Tiền mặt</th><th scope="col" class="px-4 py-3">Chuyển khoản</th><th scope="col" class="px-4 py-3">Khác</th><th scope="col" class="px-4 py-3">Tổng thu</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-terrasse-ink/15 tabular-nums">
                        @foreach ($daily as $date => $day)
                            <tr class="text-right">
                                <th scope="row" class="px-4 py-3 text-left font-medium"><time datetime="{{ $date }}">{{ \Illuminate\Support\Facades\Date::parse($date)->format('d/m/Y') }}</time></th>
                                <td class="px-4 py-3">{{ number_format($day['orders'], 0, ',', '.') }}</td>
                                <td class="px-4 py-3"><x-customer.vnd :amount="$day['subtotal']" /></td>
                                <td class="px-4 py-3"><x-customer.vnd :amount="$day['discount']" /></td>
                                <td class="px-4 py-3"><x-customer.vnd :amount="$day['delivery']" /></td>
                                <td class="px-4 py-3"><x-customer.vnd :amount="$dailyFees[$date]" /></td><td class="px-4 py-3"><x-customer.vnd :amount="$dailyChannels[$date]['cash']" /></td><td class="px-4 py-3"><x-customer.vnd :amount="$dailyChannels[$date]['bank_transfer']" /></td><td class="px-4 py-3"><x-customer.vnd :amount="$dailyChannels[$date]['other']" /></td><td class="px-4 py-3 font-semibold"><x-customer.vnd :amount="$day['total']" /></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</x-layouts::admin>
