@use('App\Enums\TableSessionStatus')

<x-layouts::customer title="Gọi món tại bàn">
    <section class="mx-auto max-w-3xl space-y-6 py-6">
        <header>
            <p>Tầng {{ $seat->floor }}</p>
            <h1 class="text-2xl font-semibold">{{ $seat::KINDS[$seat->kind] }} {{ $seat->code }}</h1>
        </header>
        @include('table-orders.feedback')

        @if ($tableSession === null)
            @if($seat->activeTableSession?->billing_mode === 'prepaid')
                <p class="terrasse-panel p-4">Quét QR tại bàn để chọn món và thanh toán, hoặc nhờ nhân viên hỗ trợ.</p>
            @else
            <form method="POST" action="{{ route('table-orders.join', $seat->code) }}" class="max-w-sm space-y-4 rounded-xl border border-terrasse-ink/15 bg-terrasse-paper p-4">
                @csrf
                <flux:input name="access_code" label="Mã phiên do nhân viên cung cấp" inputmode="numeric" minlength="8" maxlength="8" pattern="[0-9]{8}" required />
                <flux:button type="submit" variant="primary">Vào phiên gọi món</flux:button>
            </form>
            @endif
        @else
            @if ($tableSession->status === TableSessionStatus::Open)
                @if($tableSession->billing_mode === 'prepaid')
                    <section class="terrasse-panel space-y-3 p-4"><h2 class="font-semibold">Gọi món và thanh toán</h2><p>Chọn món, xem tạm tính và thanh toán trên điện thoại. Đơn hoàn tất sau khi nhân viên xác nhận đã thu tiền.</p><flux:button variant="primary" :href="route('table-orders.pos', $seat->code)">Chọn món / thanh toán</flux:button></section>
                    @foreach($tableSession->invoices()->latest()->get() as $invoice)
                        <p><flux:link :href="route('table-orders.invoice.show', [$seat->code, $invoice])">{{ $invoice->number }} · {{ $invoice->status === 'paid' ? 'Đã thanh toán' : 'Chờ thanh toán' }}</flux:link></p>
                    @endforeach
                @else
                <section class="space-y-3">
                    <h2 class="text-lg font-semibold">Chọn món</h2>
                    @include('table-orders.form', ['action' => route('table-orders.store', $seat->code), 'submitLabel' => 'Gửi lượt gọi'])
                </section>
                @if ($tableSession->checkout_requested_at !== null)
                    <p role="status">Đã yêu cầu tính tiền. Nhân viên sẽ xác nhận tại bàn.</p>
                @else
                    <form method="POST" action="{{ route('table-orders.checkout', $seat->code) }}">
                        @csrf
                        <input type="hidden" name="session_reference" value="{{ $tableSession->reference }}">
                        <flux:button type="submit">Yêu cầu tính tiền</flux:button>
                    </form>
                @endif
                @endif
            @else
                <p class="rounded-lg border border-terrasse-ink/15 bg-terrasse-paper p-3">{{ $tableSession->status->label() }} · Đã khóa gọi thêm</p>
            @endif

            @if ($tableSession->billed_at !== null)
                @include('table-orders.bill')
            @endif

            <section class="space-y-3">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <h2 class="text-lg font-semibold">Lượt đã gửi</h2>
                    <flux:link :href="route('table-orders.show', $seat->code)">Cập nhật trạng thái</flux:link>
                </div>
                @forelse ($rounds as $round)
                    @include('table-orders.round', ['operational' => false])
                @empty
                    <p class="text-sm">Chưa gửi lượt gọi nào.</p>
                @endforelse
            </section>
        @endif
    </section>
</x-layouts::customer>
