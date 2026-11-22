<dl class="grid gap-4 rounded-xl border border-terrasse-ink/15 bg-terrasse-paper p-4 sm:grid-cols-2">
    <div><dt class="text-sm">Mã đặt chỗ</dt><dd class="font-mono font-bold">{{ $reservation->code }}</dd></div>
    <div><dt class="text-sm">Trạng thái</dt><dd>{{ $reservation->status->label() }}</dd></div>
    <div>
        <dt class="text-sm">Vị trí</dt>
        <dd>Tầng {{ $reservation->seat->floor }} · {{ \App\Models\ReservationSeat::KINDS[$reservation->seat->kind] }} {{ $reservation->seat->code }} · {{ $reservation->party_size }} khách</dd>
    </div>
    <div><dt class="text-sm">Ngày tạo đặt bàn</dt><dd><x-customer.order-time :date="$reservation->created_at" /></dd></div>
    <div><dt class="text-sm">Giờ hẹn</dt><dd><x-customer.order-time :date="$reservation->scheduled_at" /></dd></div>
    <div><dt class="text-sm">Liên hệ</dt><dd class="wrap-anywhere">{{ $reservation->contact_name }} · {{ $reservation->phone }}</dd></div>
    @if ($reservation->arrived_at !== null)
        <div><dt class="text-sm">Giờ đến</dt><dd><x-customer.order-time :date="$reservation->arrived_at" /></dd></div>
    @endif
    @if ($reservation->completed_at !== null)
        <div><dt class="text-sm">Giờ trả chỗ</dt><dd><x-customer.order-time :date="$reservation->completed_at" /></dd></div>
    @endif
</dl>
