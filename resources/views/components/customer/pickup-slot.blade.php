@props(['date' => null, 'time' => null, 'required' => true, 'slots' => null, 'dayLabel' => 'Ngày nhận', 'timeLabel' => 'Giờ nhận (giờ Việt Nam)'])

@php($selectedDate = old('pickup_date', $date))
<div class="grid gap-3 sm:grid-cols-2">
    <flux:input type="date" name="pickup_date" :label="$dayLabel"
        :min="now(\App\Services\BookingRules::TIMEZONE)->toDateString()"
        :value="is_string($selectedDate) ? $selectedDate : null" :$required />
    <flux:select name="pickup_clock" :label="$timeLabel" :$required>
        <flux:select.option value="">Chọn giờ</flux:select.option>
        @foreach ($slots ?? \App\Services\BookingRules::times() as $slot)
            <flux:select.option :value="$slot" :selected="old('pickup_clock', $time) === $slot">{{ $slot }}</flux:select.option>
        @endforeach
    </flux:select>
</div>
<p class="mt-2 text-sm">Chọn giờ cách thời điểm xác nhận ít nhất 60 phút.</p>
