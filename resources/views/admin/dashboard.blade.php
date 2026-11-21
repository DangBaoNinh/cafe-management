@use('App\Enums\OrderStatus')

<x-layouts::admin title="Tổng quan quản trị">
    @php
        $placedOrders = array_sum($statuses);
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
                <p class="terrasse-muted text-sm">{{ $from->format('d/m/Y') }} – {{ $to->format('d/m/Y') }} · Theo ngày hoàn tất của đơn đã thanh toán.</p>
            </header>
            <dl class="grid grid-cols-2 gap-3 xl:grid-cols-5">
                <div class="terrasse-revenue-total col-span-2 rounded-md p-4 xl:col-span-1">
                    <dt class="text-sm">Tổng thu</dt>
                    <dd class="terrasse-display mt-2 text-3xl font-semibold tabular-nums wrap-anywhere"><x-customer.vnd :amount="$totals['total']" /></dd>
                </div>
                <div class="terrasse-panel rounded-md p-4">
                    <dt class="text-sm">Đơn hoàn tất đã thanh toán</dt>
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
                                    <span class="terrasse-report-bar" style="--bar-height: {{ round($day['total'] * 100 / $peakRevenue, 2) }}%" aria-hidden="true"></span>
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
                <dl class="grid grid-cols-1 gap-x-4 gap-y-2 sm:grid-cols-2">
                    @foreach (OrderStatus::cases() as $status)
                        <div class="flex items-center justify-between gap-2 border-b border-terrasse-ink/10 pb-2">
                            <dt><x-terrasse.status :$status /></dt>
                            <dd class="text-sm font-semibold tabular-nums">{{ number_format($statuses[$status->value], 0, ',', '.') }}</dd>
                        </div>
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
                            <th scope="col" class="px-4 py-3">Tổng thu</th>
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
                                <td class="px-4 py-3 font-semibold"><x-customer.vnd :amount="$day['total']" /></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</x-layouts::admin>
