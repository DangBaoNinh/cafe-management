@props(['bankAvailable', 'label' => 'Kênh thu phụ phí'])

<flux:select name="channel" :$label required>
    <flux:select.option value="counter">Tại quầy</flux:select.option>
    @if ($bankAvailable)
        <flux:select.option value="app" :selected="old('channel') === 'app'">Qua ứng dụng (chuyển khoản)</flux:select.option>
    @endif
</flux:select>
