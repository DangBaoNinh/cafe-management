@props(['floor', 'seats', 'selectable' => false, 'kind' => null, 'partySize' => null, 'operational' => false, 'preferredSeatId' => null])

<section class="space-y-3 rounded-2xl border border-terrasse-ink/20 bg-terrasse-paper p-3 sm:p-5" aria-labelledby="floor-{{ $floor }}">
    <h2 id="floor-{{ $floor }}" class="terrasse-display text-2xl font-semibold">Tầng {{ $floor }}</h2>
    <div class="space-y-4 rounded-xl border border-terrasse-ink/15 bg-terrasse-cream/60 p-3 sm:p-4">
        @foreach (['Bàn 4 chỗ' => [1, 5], 'Bàn 2 chỗ' => [6, 10], 'Phòng họp' => [11, 11]] as $zone => [$first, $last])
            <div class="space-y-2">
                <h3 class="text-sm font-medium">{{ $zone }}</h3>
                <ul @class(['grid gap-2', 'grid-cols-2 sm:grid-cols-3 lg:grid-cols-5' => $first !== $last])>
                    @foreach ($seats->whereBetween('position', [$first, $last]) as $seat)
                        @php
                            $availability = $seat->availability();
                            $eligible = $selectable && $availability === 'free' && $seat->kind === $kind && $seat->capacity >= $partySize;
                        @endphp
                        <li @class([
                            'min-w-0 space-y-2 rounded-xl border p-2.5 text-sm',
                            'border-terrasse-ink/20 bg-terrasse-paper' => $availability === 'free',
                            'border-amber-600/40 bg-amber-100/80 text-amber-950' => $availability === 'held',
                            'border-terrasse-danger/25 bg-terrasse-danger-surface text-terrasse-danger' => $availability === 'occupied',
                            'border-dashed border-terrasse-ink/30' => $availability === 'inactive',
                            'has-checked:ring-2 has-checked:ring-terrasse-ink' => $eligible,
                        ]) data-seat="{{ $seat->code }}" data-availability="{{ $availability }}">
                            @if ($eligible)
                                <label class="flex cursor-pointer items-center gap-2 font-semibold">
                                    <input type="radio" name="seat_id" value="{{ $seat->id }}" required @checked(in_array(old('seat_id', $preferredSeatId), [$seat->id, (string) $seat->id], true))>
                                    {{ $seat->code }}
                                </label>
                            @else
                                <p class="font-semibold">{{ $seat->code }}</p>
                            @endif
                            <p>{{ $seat->capacity }} người · {{ $seat->availabilityLabel() }}</p>
                            @if($seat->activeReservation)<p>Đã đặt: {{ $seat->activeReservation->scheduled_at?->setTimezone('Asia/Ho_Chi_Minh')->format('H:i d/m/Y') }}</p>@endif
                            @if ($operational)
                                @include('operations.reservations.seat-actions')
                            @endif
                        </li>
                    @endforeach
                </ul>
            </div>
        @endforeach
    </div>
</section>
