@props(['date' => null, 'time' => null, 'required' => true])

@php($selectedDate = old('pickup_date', $date))
<div class="grid gap-3 sm:grid-cols-2">
    <flux:input type="date" name="pickup_date" label="Ngày nhận"
        :min="now(\App\Services\BookingRules::TIMEZONE)->toDateString()"
        :value="is_string($selectedDate) ? $selectedDate : null" :$required />
    <flux:select name="pickup_clock" label="Giờ nhận (giờ Việt Nam)" :$required>
        <flux:select.option value="">Chọn giờ</flux:select.option>
        @foreach (\App\Services\BookingRules::times() as $slot)
            <flux:select.option :value="$slot" :selected="old('pickup_clock', $time) === $slot">{{ $slot }}</flux:select.option>
        @endforeach
    </flux:select>
</div>
<p class="mt-2 text-sm">Chọn giờ nhận cách thời điểm xác nhận ít nhất 60 phút.</p>
