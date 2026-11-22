@use('App\Enums\TableRoundStatus')
@use('App\Enums\TableSessionStatus')

<x-layouts::admin title="Phiên gọi món">
    <section class="mx-auto max-w-4xl space-y-6">
        <div class="space-y-1">
            <flux:link :href="route('operations.table-sessions.index')">Về danh sách bàn và phòng</flux:link>
            <h1 class="text-2xl font-semibold">Tầng {{ $tableSession->seat->floor }} · {{ $tableSession->seat->code }}</h1>
            <p>{{ $tableSession->status->label() }}</p>
        </div>
        @include('table-orders.feedback')

        @if ($tableSession->reservation)
            <flux:link :href="route('operations.reservations.show', $tableSession->reservation->code)">Xem lượt đặt và đối chiếu phụ phí</flux:link>
        @endif

        @if ($tableSession->status === TableSessionStatus::Open)
            <section class="flex flex-wrap items-center gap-5 rounded-xl border border-terrasse-ink/15 bg-terrasse-paper p-4">
                <a href="{{ route('operations.table-sessions.qr', $tableSession->seat->code) }}" aria-label="Mở mã QR của bàn hoặc phòng">
                    <img src="{{ route('operations.table-sessions.qr', $tableSession->seat->code) }}" width="128" height="128" alt="Mã QR gọi món tại {{ $tableSession->seat->code }}">
                </a>
                <div class="space-y-2">
                    <h2 class="font-semibold">Mã phiên cho khách</h2>
                    <p class="text-2xl font-semibold tabular-nums tracking-widest">{{ $tableSession->access_code }}</p>
                    <flux:link :href="route('table-orders.show', $tableSession->seat->code)">Trang gọi món của bàn</flux:link>
                </div>
            </section>
            <details class="rounded-xl border border-terrasse-ink/15 bg-terrasse-paper p-4" @if ($errors->has('lines') || old('lines')) open @endif>
                <summary class="cursor-pointer font-semibold">Nhập món thay khách</summary>
                <div class="pt-4">
                    @include('table-orders.form', ['action' => route('operations.table-sessions.store', $tableSession), 'submitLabel' => 'Ghi lượt gọi'])
                </div>
            </details>
        @endif

        <section class="space-y-3">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <h2 class="text-lg font-semibold">Lượt gọi</h2>
                <flux:link :href="route('operations.table-sessions.show', $tableSession)">Cập nhật trạng thái</flux:link>
            </div>
            @forelse ($rounds as $round)
                @include('table-orders.round', ['operational' => true])
            @empty
                <p class="text-sm">Chưa có lượt gọi.</p>
            @endforelse
        </section>

        @if ($tableSession->billed_at !== null)
            <flux:link :href="route('operations.invoices.tables.show', $tableSession)">Xem / in hóa đơn</flux:link>
            @include('table-orders.bill')
        @endif

        <section class="space-y-3 rounded-xl border border-terrasse-ink/15 bg-terrasse-paper p-4">
            <h2 class="text-lg font-semibold">Tính tiền và trả chỗ</h2>
            @if ($tableSession->status === TableSessionStatus::Open)
                @if ($tableSession->checkout_requested_at !== null)
                    <p role="status" class="font-semibold">Khách đã yêu cầu tính tiền.</p>
                @endif
                <form method="POST" action="{{ route('operations.table-sessions.update', [$tableSession, 'lock']) }}">
                    @csrf
                    <flux:button type="submit" variant="primary">Khóa gọi thêm</flux:button>
                </form>
            @elseif ($tableSession->status === TableSessionStatus::Closing && $tableSession->billed_at === null)
                @if ($rounds->whereIn('status', TableRoundStatus::unfinished())->isNotEmpty())
                    <p>Xử lý các lượt còn dang dở trước khi chốt hóa đơn.</p>
                @else
                    <form method="POST" action="{{ route('operations.table-sessions.update', [$tableSession, 'bill']) }}">
                        @csrf
                        <flux:button type="submit" variant="primary">Chốt hóa đơn</flux:button>
                    </form>
                @endif
            @elseif ($tableSession->status === TableSessionStatus::Closing)
                <form method="POST" action="{{ route('operations.table-sessions.update', [$tableSession, 'pay']) }}" class="max-w-xs space-y-3">
                    @csrf
                    <flux:input name="amount_vnd" type="number" min="0" step="1" label="Số tiền thực thu (₫)" :value="old('amount_vnd')" required />
                    <flux:button type="submit" variant="primary">Xác nhận đã thu</flux:button>
                </form>
            @elseif ($tableSession->status === TableSessionStatus::Paid)
                <p>Chỗ vẫn đang sử dụng. Xác nhận trả chỗ khi khách đã rời đi.</p>
                <form method="POST" action="{{ route('operations.table-sessions.update', [$tableSession, 'close']) }}">
                    @csrf
                    <flux:button type="submit" variant="primary">Xác nhận khách rời đi và trả chỗ</flux:button>
                </form>
            @else
                <p>Đã trả chỗ.</p>
            @endif
        </section>
    </section>
</x-layouts::admin>
