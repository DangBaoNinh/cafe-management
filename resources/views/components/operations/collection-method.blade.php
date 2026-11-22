<div class="space-y-2">
    <flux:select name="collection_method" label="Hình thức thanh toán khi nhận" required>
        <flux:select.option value="">Chọn hình thức thanh toán</flux:select.option>
        @foreach(\App\Enums\CollectionMethod::cases() as $method)
            <flux:select.option :value="$method->value" :selected="old('collection_method') === $method->value">{{ $method->label() }}</flux:select.option>
        @endforeach
    </flux:select>
    <p class="terrasse-muted text-sm">Chỉ hoàn tất sau khi đã nhận đủ tiền mặt hoặc kiểm tra tiền chuyển khoản đã vào tài khoản quán.</p>
    <flux:error name="collection_method" />
</div>
