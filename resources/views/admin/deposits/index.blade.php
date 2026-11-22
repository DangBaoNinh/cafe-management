<x-layouts::admin title="Quản lý tiền cọc">
    <section class="space-y-5">
        <h1>Quản lý tiền cọc đặt bàn</h1>
        <p>Chứng từ cọc được quản lý riêng với hóa đơn bán hàng. Đã hoàn gồm cọc đã trừ vào hóa đơn hoặc đã trả về ngân hàng.</p>
        <x-auth-session-status :status="session('status')" />
        @include('reservations.feedback')
        <form method="GET" class="flex flex-wrap items-end gap-3">
            <flux:select name="status" label="Trạng thái cọc">
                <flux:select.option value="">Tất cả</flux:select.option>
                @foreach(['pending' => 'Chờ chuyển cọc', 'paid' => 'Đã nhận cọc', 'settled' => 'Đã hoàn — trừ hóa đơn', 'refunded' => 'Đã hoàn — trả ngân hàng', 'refund_pending' => 'Chờ hoàn tiền', 'forfeited' => 'Giữ cọc', 'voided' => 'Đã hủy'] as $value => $label)
                    <flux:select.option :value="$value" :selected="($filters['status'] ?? '') === $value">{{ $label }}</flux:select.option>
                @endforeach
            </flux:select>
            <flux:button type="submit">Lọc tiền cọc</flux:button>
        </form>
        @forelse($deposits as $deposit)
            <article class="space-y-3 rounded-xl border p-4">
                <h2 class="font-bold">{{ $deposit->number }} · {{ $deposit->reservation->seat->code }}</h2>
                <p>{{ $deposit->reservation->contact_name }} · {{ $deposit->reservation->scheduled_at->setTimezone('Asia/Ho_Chi_Minh')->format('H:i d/m/Y') }}</p>
                <p><x-customer.vnd :amount="$deposit->amount_vnd" /> · <strong>{{ $deposit->statusLabel() }}</strong></p>
                @if($deposit->forfeit_reason)<p>Lý do giữ cọc: {{ $deposit->forfeit_reason }}</p>@endif
                <flux:button :href="route('operations.deposits.show', $deposit)" size="sm">Xem chứng từ</flux:button>
                @if($deposit->status === 'paid' && $deposit->table_invoice_id === null && $deposit->reservation->status === \App\Enums\ReservationStatus::Confirmed)
                    <details><summary class="cursor-pointer font-semibold">Giữ cọc / hủy phiếu đặt bàn</summary>
                        <form method="POST" action="{{ route('admin.deposits.forfeit', $deposit) }}" class="mt-3 space-y-3">@csrf
                            <flux:textarea name="reason" label="Lý do giữ cọc" maxlength="500" required />
                            <label><input type="checkbox" name="confirm" value="1" required> Xác nhận hủy lịch và giữ khoản cọc này.</label>
                            <flux:button type="submit" variant="danger">Xác nhận giữ cọc</flux:button>
                        </form>
                    </details>
                @endif
            </article>
        @empty<p>Không có chứng từ phù hợp.</p>@endforelse
        {{ $deposits->links() }}
    </section>
</x-layouts::admin>
