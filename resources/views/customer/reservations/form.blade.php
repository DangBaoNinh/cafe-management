        <form method="GET" action="{{ route('spaces').'#booking-form' }}" class="space-y-4 rounded-xl border border-terrasse-ink/15 bg-terrasse-paper p-4 sm:p-5">
            @if ($preferredSeat)
                <input type="hidden" name="seat_id" value="{{ $preferredSeat->id }}">
                <p>Chỗ đã chọn <strong>{{ $preferredSeat->code }}</strong> · Tầng {{ $preferredSeat->floor }} · {{ \App\Models\ReservationSeat::KINDS[$preferredSeat->kind] }}</p>
                @if ($preferredSeat->availability() !== 'free')
                    <p role="status">{{ $preferredSeat->availabilityLabel() }}. Bạn có thể chọn chỗ trống khác.</p>
                @endif
            @endif
            <x-customer.pickup-slot :date="$filters['pickup_date']" :time="$filters['pickup_clock']" :$slots day-label="Ngày đến" time-label="Giờ đến (giờ Việt Nam)" />
            <div class="grid gap-3 sm:grid-cols-3">
                <flux:select name="kind" label="Loại chỗ" required>
                    @foreach (\App\Models\ReservationSeat::KINDS as $value => $label)
                        <flux:select.option :value="$value" :selected="$filters['kind'] === $value">{{ $label }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:input name="party_size" label="Số khách" type="number" min="1" max="10" :value="$filters['party_size']" required />
                <flux:select name="floor" label="Tầng">
                    <flux:select.option value="">Cả hai tầng</flux:select.option>
                    @foreach ([1, 2] as $floor)
                        <flux:select.option :value="$floor" :selected="(int) ($filters['floor'] ?? 0) === $floor">Tầng {{ $floor }}</flux:select.option>
                    @endforeach
                </flux:select>
            </div>
            <flux:button type="submit" variant="primary">Xác nhận đặt bàn</flux:button>
        </form>
        @php
            $visibleFloors = empty($filters['floor']) ? $seats : $seats->only([(int) $filters['floor']]);
            $available = $searched && $visibleFloors->flatten()->contains(fn ($seat) =>
                $seat->kind === $filters['kind'] && $seat->capacity >= $filters['party_size'] && $seat->availability() === 'free');
        @endphp
        @if ($searched && ! $available)
            <p role="status">Chưa có chỗ trống phù hợp. Hãy thử tầng hoặc loại chỗ khác.</p>
        @endif
        <form method="POST" action="{{ route('reservations.store') }}" class="space-y-4">
            @csrf
            @if ($searched)
                @foreach (['pickup_date', 'pickup_clock', 'kind', 'party_size'] as $field)
                    <input type="hidden" name="{{ $field }}" value="{{ $filters[$field] }}">
                @endforeach
                <input type="hidden" name="booking_key" value="{{ $bookingKey }}">
            @endif
            @foreach ($visibleFloors as $floor => $floorSeats)
                <x-reservations.floor :$floor :seats="$floorSeats" :selectable="$available" :kind="$filters['kind']" :party-size="$filters['party_size']" :preferred-seat-id="$preferredSeat?->id" />
            @endforeach
            @if ($available)
                <section class="space-y-4 rounded-xl border border-terrasse-ink/15 bg-terrasse-paper p-4 sm:p-5">
                    <h2 class="text-xl font-semibold">Xác nhận giữ chỗ</h2>
                    <p>{{ $filters['pickup_clock'] }} · {{ $filters['pickup_date'] }} · {{ $filters['party_size'] }} khách</p>
                    <div class="grid gap-3 sm:grid-cols-2">
                        <flux:input name="contact_name" label="Tên liên hệ" maxlength="120" autocomplete="name"
                            :value="is_string(old('contact_name')) ? old('contact_name') : $customer->name" required />
                        <flux:input name="phone" label="Số điện thoại" type="tel" maxlength="13" autocomplete="tel"
                            :value="is_string(old('phone')) ? old('phone') : ($customer->phone ?? '')" required />
                    </div>
                    <input type="hidden" name="channel" value="app"><p class="font-semibold">Đặt cọc bàn 100.000 ₫; phòng họp 10 người 300.000 ₫ · bắt buộc chuyển khoản.</p><p>Hủy trong 30 phút từ lúc đặt và có ngân hàng trong Hồ sơ: tạo yêu cầu hoàn tiền. Không đến: quán giữ cọc. Khi thanh toán tại bàn, cọc được trừ một lần; phần dư được hoàn tiền mặt.</p>
                    <flux:button type="submit" variant="primary">Xác nhận đặt chỗ</flux:button>
                </section>
            @endif
        </form>
