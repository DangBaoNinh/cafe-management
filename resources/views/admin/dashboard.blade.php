@use('App\Enums\OrderStatus')

<x-layouts::admin title="Tổng quan quản trị">
    <div class="space-y-8">
        <header class="space-y-1">
            <h1 class="text-2xl font-semibold sm:text-3xl">Tổng quan quản trị</h1>
            <p class="text-sm">Khoảng ngày theo giờ Việt Nam (UTC+7), tối đa {{ $maxDays }} ngày.</p>
        </header>

        <form method="GET" action="{{ route('admin.dashboard') }}" class="grid gap-4 rounded-xl border border-coffee/15 bg-white p-4 sm:grid-cols-[1fr_1fr_auto]">
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
            <div class="flex flex-wrap items-center gap-4 sm:mt-7 sm:self-start">
                <flux:button type="submit" variant="primary">Xem báo cáo</flux:button>
                <flux:link :href="route('admin.dashboard')">7 ngày gần nhất</flux:link>
            </div>
        </form>

        <section aria-labelledby="revenue-heading" class="space-y-4">
            <header class="space-y-1">
                <h2 id="revenue-heading" class="text-xl font-semibold">Doanh thu</h2>
                <p class="text-sm">{{ $from->format('d/m/Y') }} – {{ $to->format('d/m/Y') }} · Theo ngày hoàn tất của đơn đã thanh toán.</p>
            </header>
            <dl class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <div class="rounded-xl bg-coffee p-5 text-white sm:col-span-2 lg:col-span-4">
                    <dt class="text-sm">Tổng thu</dt>
                    <dd class="mt-1 text-3xl font-semibold tabular-nums wrap-anywhere"><x-customer.vnd :amount="$totals['total']" /></dd>
                </div>
                <div class="rounded-xl border border-coffee/15 bg-white p-4">
                    <dt class="text-sm">Đơn hoàn tất đã thanh toán</dt>
                    <dd class="mt-1 text-xl font-semibold tabular-nums">{{ number_format($totals['orders'], 0, ',', '.') }}</dd>
                </div>
                <div class="rounded-xl border border-coffee/15 bg-white p-4">
                    <dt class="text-sm">Tạm tính</dt>
                    <dd class="mt-1 text-xl font-semibold tabular-nums wrap-anywhere"><x-customer.vnd :amount="$totals['subtotal']" /></dd>
                </div>
                <div class="rounded-xl border border-coffee/15 bg-white p-4">
                    <dt class="text-sm">Giảm giá</dt>
                    <dd class="mt-1 text-xl font-semibold tabular-nums wrap-anywhere"><x-customer.vnd :amount="$totals['discount']" /></dd>
                </div>
                <div class="rounded-xl border border-coffee/15 bg-white p-4">
                    <dt class="text-sm">Phí giao</dt>
                    <dd class="mt-1 text-xl font-semibold tabular-nums wrap-anywhere"><x-customer.vnd :amount="$totals['delivery']" /></dd>
                </div>
            </dl>
        </section>

        <section aria-labelledby="status-heading" class="space-y-4">
            <header class="space-y-1">
                <h2 id="status-heading" class="text-xl font-semibold">Trạng thái đơn theo ngày đặt</h2>
                <p class="text-sm">Tổng số đơn đặt trong khoảng đã chọn: {{ number_format(array_sum($statuses), 0, ',', '.') }}.</p>
            </header>
            <dl class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                @foreach (OrderStatus::cases() as $status)
                    <div class="rounded-xl border border-coffee/15 bg-white p-3 sm:p-4">
                        <dt class="text-sm">{{ $status->label() }}</dt>
                        <dd class="mt-1 text-lg font-semibold tabular-nums">{{ number_format($statuses[$status->value], 0, ',', '.') }}</dd>
                    </div>
                @endforeach
            </dl>
        </section>

        <section class="space-y-4">
            <h2 id="daily-revenue-heading" class="text-xl font-semibold">Doanh thu theo ngày</h2>
            <div role="region" aria-labelledby="daily-revenue-heading" tabindex="0" class="max-w-full overflow-x-auto rounded-xl border border-coffee/15 bg-white focus-visible:outline-2 focus-visible:outline-coffee">
                <table class="w-full text-sm whitespace-nowrap">
                    <thead class="border-b border-coffee/15 bg-coffee/5">
                        <tr class="text-right">
                            <th scope="col" class="px-4 py-3 text-left">Ngày hoàn tất</th>
                            <th scope="col" class="px-4 py-3">Số đơn</th>
                            <th scope="col" class="px-4 py-3">Tạm tính</th>
                            <th scope="col" class="px-4 py-3">Giảm giá</th>
                            <th scope="col" class="px-4 py-3">Phí giao</th>
                            <th scope="col" class="px-4 py-3">Tổng thu</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-coffee/15 tabular-nums">
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
