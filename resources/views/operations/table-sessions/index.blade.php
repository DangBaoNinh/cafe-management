@use('App\Enums\ReservationStatus')
<x-layouts::admin title="Nhận order">
    <section class="space-y-6">
        <header class="flex flex-wrap items-center justify-between gap-3"><div><h1>Nhận order</h1><p class="terrasse-muted text-sm">Chọn bàn để mở order hoặc tiếp tục thanh toán tạm tính đã lưu.</p></div><flux:link :href="route('operations.table-sessions.index')">Cập nhật trạng thái</flux:link></header>
        @include('table-orders.feedback')
        <div class="flex flex-wrap gap-3 text-sm" aria-label="Trạng thái bàn"><span class="pill">Trống</span><span class="pill active">Đang sử dụng</span><span class="pill">Đã đặt trước</span><span class="pill">Ngừng phục vụ</span></div>
        @foreach($seats as $floor => $floorSeats)
            <section class="space-y-3"><h2>Tầng {{ $floor }}</h2>
                <ul class="grid items-start gap-4 sm:grid-cols-2 xl:grid-cols-3">
                    @foreach($floorSeats as $seat)
                        @php($state = $seat->availability())
                        <li class="terrasse-panel min-w-0 space-y-4 p-4" data-table-code="{{ $seat->code }}">
                            <x-reservations.order-warning :seat="$seat" />
                            <form method="POST" action="{{ route('operations.table-sessions.table.order', $seat->code) }}">
                                @csrf
                                <button type="submit" class="w-full space-y-2 text-left" @disabled($seat->tableInvoices->isEmpty() && $state === 'inactive')>
                                    <span class="block text-lg font-semibold">{{ $seat::KINDS[$seat->kind] }} {{ $seat->code }}</span>
                                    <span class="pill {{ $state === 'occupied' ? 'active' : '' }}">{{ $seat->availabilityLabel() }}</span>
                                    <span class="block text-sm underline">{{ $seat->tableInvoices->isNotEmpty() ? 'Mở lại tạm tính / thanh toán' : 'Order / chọn món' }}</span>
                                </button>
                            </form>
                            @if($seat->tableInvoices->isNotEmpty())
                                <div class="space-y-2 border-t border-terrasse-ink/15 pt-3"><p class="font-semibold">Tạm tính chưa thanh toán</p>
                                    @foreach($seat->tableInvoices as $invoice)
                                        <div class="flex flex-wrap justify-between gap-2 text-sm"><flux:link :href="route('operations.invoices.prepaid.show', $invoice)">{{ $invoice->number }}</flux:link><x-customer.vnd :amount="$invoice->total_vnd" /></div>
                                    @endforeach
                                </div>
                            @endif
                            @if($seat->activeReservation && $seat->activeReservation->status === ReservationStatus::Confirmed)
                                <flux:link :href="route('operations.reservations.show', $seat->activeReservation->code)">Xem lịch đặt / nhận khách</flux:link>
                            @else
                                <form method="POST" action="{{ route('operations.table-sessions.table.status', $seat->code) }}" class="flex flex-wrap items-end gap-2">
                                    @csrf<input type="hidden" name="expected" value="{{ $state }}">
                                    <label class="grid flex-1 gap-1 text-sm">Trạng thái bàn<select class="pos-input w-full" name="status" aria-label="Trạng thái {{ $seat->code }}">
                                        <option value="free" @selected($state === 'free')>Trống</option><option value="occupied" @selected($state === 'occupied')>Đang sử dụng</option>
                                        @can('access-admin')<option value="inactive" @selected($state === 'inactive')>Ngừng phục vụ</option>@else
                                            @if($state === 'inactive')<option value="inactive" selected disabled>Ngừng phục vụ</option>@endif
                                        @endcan
                                    </select></label>
                                    <flux:button type="submit" size="sm" :disabled="$state === 'inactive' && !auth()->user()->can('access-admin')">Cập nhật</flux:button>
                                </form>
                            @endif
                            @if($seat->activeTableSession && $seat->activeTableSession->billing_mode === 'prepaid')
                                <details class="text-sm"><summary class="cursor-pointer">QR cho khách tự gọi món</summary><img class="mt-3 bg-white" src="{{ route('operations.table-sessions.qr', $seat->code) }}" width="180" height="180" alt="QR gọi món bàn {{ $seat->code }}"></details>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </section>
        @endforeach
        <flux:link :href="route('operations.invoices.history', ['source' => 'table'])">Xem lịch sử hóa đơn bàn / phòng</flux:link>
    </section>
</x-layouts::admin>
