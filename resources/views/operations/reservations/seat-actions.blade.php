@if ($seat->activeTableSession !== null)
    <flux:link :href="route('operations.table-sessions.show', $seat->activeTableSession)">Xem phiên gọi món</flux:link>
@elseif ($seat->activeReservation !== null)
    <flux:link :href="route('operations.reservations.show', $seat->activeReservation->code)">Xem lượt đặt</flux:link>
@elseif ($availability === 'free' || $seat->walk_in_at !== null)
    <form method="POST" action="{{ route('operations.reservations.walk-in', $seat->id) }}">
        @csrf
        <input type="hidden" name="arriving" value="{{ $seat->walk_in_at === null ? '1' : '0' }}">
        <button type="submit" class="cursor-pointer text-left text-sm font-semibold underline underline-offset-2">
            {{ $seat->walk_in_at === null ? 'Ghi khách trực tiếp' : 'Trả chỗ khách trực tiếp' }}
        </button>
    </form>
@endif
@can('access-admin')
    @if (in_array($availability, ['free', 'inactive'], true))
        <form method="POST" action="{{ route('operations.reservations.seat-active', $seat->id) }}">
            @csrf
            <input type="hidden" name="active" value="{{ $seat->is_active ? '0' : '1' }}">
            <button type="submit" class="cursor-pointer text-left text-sm underline underline-offset-2">
                {{ $seat->is_active ? 'Ngừng phục vụ' : 'Mở phục vụ' }}
            </button>
        </form>
    @endif
@endcan
