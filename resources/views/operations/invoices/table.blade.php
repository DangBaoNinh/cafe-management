<x-layouts::admin :title="'Hóa đơn bàn BH-'.$tableSession->id">
    <article class="invoice-sheet mx-auto max-w-3xl space-y-6">
        <div class="flex flex-wrap gap-3" data-no-print>
            <flux:link :href="route($tableSession->paid_at ? 'operations.invoices.history' : 'operations.invoices.index')">Về danh sách hóa đơn</flux:link>
            <flux:button type="button" data-print-invoice>In hóa đơn</flux:button>
        </div>
        <header class="space-y-2 border-b border-terrasse-ink/15 pb-4">
            <p class="terrasse-display text-3xl">Toàn Café &amp; Jardin</p>
            <h1>Hóa đơn bàn BH-{{ $tableSession->id }}</h1>
            <p>{{ $tableSession->seat->code }} · Tầng {{ $tableSession->seat->floor }}</p>
            <p>Lập lúc <x-customer.order-time :date="$tableSession->billed_at" /></p>
            <p>{{ $tableSession->paid_at ? 'Đã thanh toán tại quầy' : 'Chờ thanh toán tại quầy' }}</p>
            @if($tableSession->paid_at)<p>Thanh toán lúc <x-customer.order-time :date="$tableSession->paid_at" /></p>@endif
        </header>
        <section class="space-y-3">
            <h2 class="text-lg font-semibold">Món đã phục vụ</h2>
            @forelse($rounds as $round)
                @include('table-orders.round', ['operational' => false])
            @empty
                <p>Không có món đã phục vụ.</p>
            @endforelse
        </section>
        @include('table-orders.bill')
        <div data-no-print><flux:button :href="route('operations.table-sessions.show', $tableSession)">Mở phiên để thu tiền / trả chỗ</flux:button></div>
    </article>
</x-layouts::admin>
