@props(['option', 'nameField', 'nameLabel'])

@php
    $code = old('code', $option->code);
    $name = old($nameField, $option->{$nameField});
    $price = old('price_vnd', $option->price_vnd);
    $status = old('is_active', (string) (int) $option->is_active);
@endphp

<flux:input name="code" label="Mã" :value="is_string($code) ? $code : ''" required maxlength="40"
    description="Dùng chữ cái không dấu, chữ số và dấu gạch nối." />
<flux:input :name="$nameField" :label="$nameLabel" :value="is_string($name) ? $name : ''" required maxlength="255" />
<flux:input name="price_vnd" label="Giá (VND)" type="number" min="0" max="4294967295" step="1"
    :value="is_scalar($price) ? $price : ''" required />
<flux:select name="is_active" label="Trạng thái" required>
    <flux:select.option value="1" :selected="$status === '1'">Đang bán</flux:select.option>
    <flux:select.option value="0" :selected="$status === '0'">Ngừng bán</flux:select.option>
</flux:select>
