<x-layouts::customer title="Lịch sử đặt bàn">
    <section class="mx-auto max-w-3xl space-y-5">
        <header class="space-y-3">
            <h1 class="terrasse-display text-3xl font-semibold">Lịch sử đặt bàn</h1>
            <p>Theo dõi lịch sắp đến và xem lại chi tiết các lần đặt bàn, phòng họp.</p>
            <flux:button variant="primary" :href="route('spaces').'#booking-form'">Đặt chỗ tại quán</flux:button>
        </header>
        <nav class="flex flex-wrap gap-3" aria-label="Nhóm lịch đặt bàn">
            @foreach(['all' => 'Tất cả', 'open' => 'Đang đặt', 'history' => 'Lịch sử'] as $value => $label)
                <a href="{{ route('reservations.index', ['scope' => $value]) }}" @if($scope === $value) aria-current="page" @endif
                    @class(['rounded-lg border px-4 py-2 font-semibold', 'bg-terrasse-ink text-white' => $scope === $value])>{{ $label }}</a>
            @endforeach
        </nav>
        <form method="GET" action="{{ route('reservations.index') }}" class="space-y-4 rounded-xl border border-terrasse-ink/15 bg-terrasse-paper p-4">
            <input type="hidden" name="scope" value="{{ $scope }}">
            <div class="grid gap-3 sm:grid-cols-3">
                <flux:input type="date" name="from" label="Ngày đến từ" :value="$filters['from'] ?? ''" />
                <flux:input type="date" name="to" label="Ngày đến đến" :value="$filters['to'] ?? ''" />
                <flux:select name="status" label="Trạng thái">
                    <flux:select.option value="">Tất cả trạng thái</flux:select.option>
                    @foreach(\App\Enums\ReservationStatus::cases() as $status)
                        <flux:select.option :value="$status->value" :selected="($filters['status'] ?? '') === $status->value">{{ $status->label() }}</flux:select.option>
                    @endforeach
                </flux:select>
            </div>
            <div class="flex flex-wrap items-center gap-3">
                <flux:button type="submit" variant="primary">Lọc lịch đặt bàn</flux:button>
                <flux:link :href="route('reservations.index', ['scope' => $scope])">Xóa bộ lọc</flux:link>
            </div>
        </form>
        <p class="text-sm" role="status">{{ $reservations->total() }} lượt đặt bàn</p>
        <ul class="space-y-4">
            @forelse($reservations as $reservation)
                <li class="space-y-3 rounded-xl border border-terrasse-ink/15 bg-terrasse-paper p-4">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <h2 class="text-lg font-semibold">{{ $reservation->seat->code }} · Tầng {{ $reservation->seat->floor }}</h2>
                        <span class="rounded-lg border px-3 py-1 text-sm font-semibold">{{ $reservation->status->label() }}</span>
                    </div>
                    <p class="break-all text-sm">Mã đặt: <strong>{{ $reservation->code }}</strong></p>
                    <dl class="grid gap-2 text-sm sm:grid-cols-2">
                        <div><dt>Ngày tạo</dt><dd><x-customer.order-time :date="$reservation->created_at" /></dd></div>
                        <div><dt>Ngày / giờ đến</dt><dd><x-customer.order-time :date="$reservation->scheduled_at" /></dd></div>
                        <div><dt>Số khách</dt><dd>{{ $reservation->party_size }} người</dd></div>
                        @if($reservation->deposit)
                            <div><dt>Tiền cọc</dt><dd><x-customer.vnd :amount="$reservation->deposit->amount_vnd" /> · {{ $reservation->deposit->statusLabel() }}</dd></div>
                        @endif
                    </dl>
                    <flux:button :href="route('reservations.show', $reservation->code)" size="sm">Xem chi tiết</flux:button>
                </li>
            @empty
                <li class="rounded-xl border p-5">Chưa có lượt đặt bàn phù hợp.</li>
            @endforelse
        </ul>
        {{ $reservations->links() }}
    </section>
</x-layouts::customer>
