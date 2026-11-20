@php
    $title = $coupon->exists ? 'Chỉnh sửa mã giảm giá' : 'Thêm mã giảm giá';
    $type = old('discount_type', $coupon->discount_type->value);
    $active = old('is_active', (string) (int) $coupon->is_active);
    $code = old('code', $coupon->code);
    $value = old('discount_value', $coupon->discount_value);
@endphp

<x-layouts::admin :title="$title">
    <section class="max-w-2xl space-y-5">
        <flux:link :href="route('admin.coupons.index')">Về danh sách mã giảm giá</flux:link>
        <h1 class="text-2xl font-semibold">{{ $title }}</h1>

        <form method="POST" action="{{ $coupon->exists ? route('admin.coupons.update', $coupon) : route('admin.coupons.store') }}" class="space-y-5">
            @csrf
            @if ($coupon->exists)
                @method('PATCH')
            @endif

            <div class="grid gap-4 sm:grid-cols-2">
                <flux:input name="code" label="Mã giảm giá" :value="is_string($code) ? $code : ''" required maxlength="40" />
                <flux:select name="is_active" label="Trạng thái" required>
                    <flux:select.option value="1" :selected="$active === '1'">Bật</flux:select.option>
                    <flux:select.option value="0" :selected="$active === '0'">Tắt</flux:select.option>
                </flux:select>
                <flux:select name="discount_type" label="Loại giảm" required>
                    @foreach (\App\Enums\CouponDiscountType::cases() as $option)
                        <flux:select.option :value="$option->value" :selected="$type === $option->value">{{ $option->label() }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:input name="discount_value" label="Mức giảm" :value="is_scalar($value) ? $value : ''"
                    inputmode="numeric" pattern="[0-9]+" maxlength="19" required
                    description="Nhập số tiền VND hoặc phần trăm từ 1 đến 100 theo loại đã chọn." />
                @foreach ([
                    'min_subtotal_vnd' => ['Tạm tính tối thiểu (VND)', 'Dùng 0 nếu không yêu cầu mức tối thiểu.'],
                    'max_discount_vnd' => ['Mức giảm tối đa (VND)', 'Chỉ áp dụng cho phần trăm. Để trống nếu không giới hạn.'],
                    'total_limit' => ['Giới hạn tổng lượt', ''],
                    'per_user_limit' => ['Giới hạn lượt mỗi khách', ''],
                ] as $field => [$label, $description])
                    @php($fieldValue = old($field, $coupon->{$field}))
                    <flux:input :name="$field" :label="$label" :value="is_scalar($fieldValue) ? $fieldValue : ''"
                        :description="$description" inputmode="numeric" pattern="[0-9]+" maxlength="19"
                        :required="$field === 'min_subtotal_vnd'" />
                @endforeach
            </div>
            <p class="text-sm">Để trống các giới hạn lượt nếu không giới hạn.</p>

            <fieldset class="space-y-2">
                <legend class="font-medium">Thời gian hiệu lực (giờ Việt Nam)</legend>
                <div class="grid gap-4 sm:grid-cols-2">
                    @foreach (['starts_at' => 'Bắt đầu', 'ends_at' => 'Kết thúc'] as $field => $label)
                        @php($date = old($field, $coupon->{$field}?->setTimezone(\App\Models\Coupon::LOCAL_TIMEZONE)->format(\App\Models\Coupon::FORM_DATE_FORMAT)))
                        <flux:input :name="$field" :label="$label" type="datetime-local"
                            :value="is_string($date) ? $date : ''" required />
                    @endforeach
                </div>
            </fieldset>

            <flux:button type="submit" variant="primary">Lưu mã giảm giá</flux:button>
        </form>
    </section>
</x-layouts::admin>
