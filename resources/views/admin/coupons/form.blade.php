@php
    $title = $coupon->exists ? 'Chỉnh sửa mã giảm giá' : 'Thêm mã giảm giá';
    $type = old('discount_type', $coupon->discount_type->value);
    $active = old('is_active', (string) (int) $coupon->is_active);
    $code = old('code', $coupon->code);
    $value = old('discount_value', $coupon->discount_value);
    $minimum = old('min_subtotal_vnd', $coupon->min_subtotal_vnd);
    $maximum = old('max_discount_vnd', $coupon->max_discount_vnd);
    $nowLocal = now(\App\Models\Coupon::LOCAL_TIMEZONE)->startOfMinute()->format(\App\Models\Coupon::FORM_DATE_FORMAT);
@endphp

<x-layouts::admin :title="$title">
    <section class="max-w-6xl space-y-5">
        <flux:link :href="route('admin.coupons.index')">Về danh sách mã giảm giá</flux:link>
        <header class="coupon-form-header">
            <p class="coupon-summary__eyebrow">Thiết lập ưu đãi</p>
            <h1>{{ $title }}</h1>
            <p>Chọn loại giảm trước. Biểu mẫu chỉ hiện các cấu hình cần thiết cho loại đó.</p>
        </header>

        <div class="coupon-form-layout">
        <form method="POST" action="{{ $coupon->exists ? route('admin.coupons.update', $coupon) : route('admin.coupons.store') }}" class="space-y-5" data-coupon-form>
            @csrf
            @if ($coupon->exists)
                @method('PATCH')
            @endif

            <div class="space-y-5 rounded-xl border border-terrasse-ink/15 bg-terrasse-paper p-4 sm:p-6">
                <section class="space-y-4" aria-labelledby="coupon-basic-heading">
                    <div><p class="coupon-summary__eyebrow">01 · Nhận diện</p><h2 id="coupon-basic-heading" class="text-lg font-semibold">Mã và trạng thái</h2></div>
                    <div class="grid gap-4 sm:grid-cols-2">
                <flux:input name="code" label="Mã giảm giá" :value="is_string($code) ? $code : ''" placeholder="Ví dụ: CAFE15" required maxlength="40" />
                <flux:select name="is_active" label="Trạng thái" required>
                    <flux:select.option value="1" :selected="$active === '1'">Bật</flux:select.option>
                    <flux:select.option value="0" :selected="$active === '0'">Tắt</flux:select.option>
                </flux:select>
                    </div>
                </section>
                <section class="space-y-4 border-t border-terrasse-ink/10 pt-5" aria-labelledby="coupon-value-heading">
                    <div><p class="coupon-summary__eyebrow">02 · Giá trị</p><h2 id="coupon-value-heading" class="text-lg font-semibold">Mức giảm và điều kiện</h2></div>
                <flux:select name="discount_type" label="Loại giảm" required>
                    @foreach (\App\Enums\CouponDiscountType::cases() as $option)
                        <flux:select.option :value="$option->value" :selected="$type === $option->value">{{ $option->label() }}</flux:select.option>
                    @endforeach
                </flux:select>
                <p class="coupon-type-help" data-coupon-type-help aria-live="polite"></p>
                <div class="grid gap-4 sm:grid-cols-2">
                <div class="coupon-number-field" data-coupon-stepper data-field="discount_value" data-step-fixed="5000" data-step-percentage="1" data-min="1" data-max-percentage="100">
                    <label class="coupon-number-field__label" for="discount_value">Mức giảm</label>
                    <div class="coupon-number-control @error('discount_value') coupon-number-control--invalid @enderror">
                        <button type="button" data-coupon-step="-1" aria-label="Giảm mức giảm">−</button>
                        <input id="discount_value" name="discount_value" type="number" min="1" step="1" :value="is_scalar($value) ? $value : ''" inputmode="numeric" required />
                        <span data-coupon-unit aria-hidden="true">{{ $type === 'percentage' ? '%' : 'đ' }}</span>
                        <button type="button" data-coupon-step="1" aria-label="Tăng mức giảm">+</button>
                    </div>
                    <p class="coupon-number-field__help" data-discount-help>Nhập số tiền muốn giảm.</p>
                    <flux:error name="discount_value" />
                </div>
                    <div class="coupon-number-field" data-coupon-stepper data-field="min_subtotal_vnd" data-step="10000" data-min="0">
                        <label class="coupon-number-field__label" for="min_subtotal_vnd">Đơn tối thiểu</label>
                        <div class="coupon-number-control @error('min_subtotal_vnd') coupon-number-control--invalid @enderror">
                            <button type="button" data-coupon-step="-1" aria-label="Giảm giá trị đơn tối thiểu">−</button>
                            <input id="min_subtotal_vnd" name="min_subtotal_vnd" type="number" min="0" step="1" :value="is_scalar($minimum) ? $minimum : ''" inputmode="numeric" required />
                            <span aria-hidden="true">đ</span>
                            <button type="button" data-coupon-step="1" aria-label="Tăng giá trị đơn tối thiểu">+</button>
                        </div>
                        <p class="coupon-number-field__help">Đặt 0 nếu áp dụng cho mọi đơn hàng.</p>
                        <flux:error name="min_subtotal_vnd" />
                    </div>
                    <div data-coupon-cap class="coupon-number-field" data-coupon-stepper data-field="max_discount_vnd" data-step="10000" data-min="1">
                        <label class="coupon-number-field__label" for="max_discount_vnd">Giảm tối đa</label>
                        <div class="coupon-number-control @error('max_discount_vnd') coupon-number-control--invalid @enderror">
                            <button type="button" data-coupon-step="-1" aria-label="Giảm mức giảm tối đa">−</button>
                            <input id="max_discount_vnd" name="max_discount_vnd" type="number" min="1" step="1" :value="is_scalar($maximum) ? $maximum : ''" inputmode="numeric" />
                            <span aria-hidden="true">đ</span>
                            <button type="button" data-coupon-step="1" aria-label="Tăng mức giảm tối đa">+</button>
                        </div>
                        <p class="coupon-number-field__help">Không bắt buộc. Chỉ dùng khi giảm theo phần trăm.</p>
                        <flux:error name="max_discount_vnd" />
                    </div>
                    </div>
                </section>

                <section class="space-y-4 border-t border-terrasse-ink/10 pt-5" aria-labelledby="coupon-time-heading">
                    <div><p class="coupon-summary__eyebrow">03 · Hiệu lực</p><h2 id="coupon-time-heading" class="text-lg font-semibold">Thời gian · giờ Việt Nam</h2></div>
                    <div class="grid gap-4 sm:grid-cols-2">
                        @foreach (['starts_at' => 'Bắt đầu', 'ends_at' => 'Kết thúc'] as $field => $label)
                            @php
                                $storedDate = $coupon->{$field}?->setTimezone(\App\Models\Coupon::LOCAL_TIMEZONE)->format(\App\Models\Coupon::FORM_DATE_FORMAT);
                                $date = old($field, $storedDate);
                            @endphp
                            <flux:input :name="$field" :label="$label" type="datetime-local"
                                :value="is_string($date) ? $date : ''"
                                :min="$field === 'ends_at' ? $nowLocal : null"
                                :data-original="$storedDate"
                                data-coupon-date required />
                        @endforeach
                    </div>
                    <p class="coupon-time-warning" data-coupon-time-warning role="alert" hidden>Không được chọn thời gian trong quá khứ.</p>
                </section>

                <details class="coupon-advanced border-t border-terrasse-ink/10 pt-5" @if($errors->hasAny(['total_limit', 'per_user_limit'])) open @endif>
                    <summary>Giới hạn lượt sử dụng <span>Không bắt buộc</span></summary>
                    <div class="mt-4 grid gap-4 sm:grid-cols-2">
                        @foreach (['total_limit' => 'Tổng lượt', 'per_user_limit' => 'Mỗi khách'] as $field => $label)
                            @php($fieldValue = old($field, $coupon->{$field}))
                            <div class="coupon-number-field" data-coupon-stepper data-field="{{ $field }}" data-step="1" data-min="1">
                                <label class="coupon-number-field__label" for="{{ $field }}">{{ $label }}</label>
                                <div class="coupon-number-control @error($field) coupon-number-control--invalid @enderror">
                                    <button type="button" data-coupon-step="-1" aria-label="Giảm {{ strtolower($label) }}">−</button>
                                    <input id="{{ $field }}" name="{{ $field }}" type="number" min="1" step="1" :value="is_scalar($fieldValue) ? $fieldValue : ''" inputmode="numeric" />
                                    <span aria-hidden="true">lượt</span>
                                    <button type="button" data-coupon-step="1" aria-label="Tăng {{ strtolower($label) }}">+</button>
                                </div>
                                <p class="coupon-number-field__help">Để trống nếu không giới hạn.</p>
                                <flux:error :name="$field" />
                            </div>
                        @endforeach
                    </div>
                </details>
                <div class="flex flex-wrap gap-3 border-t border-terrasse-ink/10 pt-5"><flux:button type="submit" variant="primary">Lưu mã giảm giá</flux:button><flux:button :href="route('admin.coupons.index')">Hủy</flux:button></div>
            </div>
        </form>
        <aside class="coupon-form-preview" aria-labelledby="coupon-preview-heading">
            <p class="coupon-summary__eyebrow">Xem trước trực tiếp</p>
            <h2 id="coupon-preview-heading">Mã mô phỏng</h2>
            <p class="coupon-form-preview__intro">Kiểm tra cách ưu đãi sẽ xuất hiện với khách hàng trước khi lưu.</p>
            <article class="coupon-summary" aria-live="polite">
                <div class="coupon-summary__seal" aria-hidden="true">ƯĐ</div>
                <div class="min-w-0 space-y-1">
                    <p class="coupon-summary__eyebrow">Phiếu ưu đãi</p>
                    <p class="coupon-summary__code" data-coupon-preview-code>{{ $code ?: 'CAFE15' }}</p>
                </div>
                <span class="terrasse-pill coupon-summary__status" data-tone="{{ $active === '1' ? 'green' : 'rose' }}" data-coupon-preview-status>{{ $active === '1' ? 'Đang bật' : 'Đang tắt' }}</span>
                <div class="coupon-summary__offer">
                    <p class="coupon-summary__amount" data-coupon-preview-amount>Nhập mức giảm</p>
                    <p class="terrasse-muted text-sm" data-coupon-preview-cap></p>
                </div>
                <dl class="coupon-summary__details">
                    <div><dt>Điều kiện</dt><dd data-coupon-preview-minimum>Áp dụng cho mọi đơn</dd></div>
                    <div><dt>Loại ưu đãi</dt><dd data-coupon-preview-type>Giảm theo phần trăm</dd></div>
                    <div><dt>Trạng thái</dt><dd data-coupon-preview-active>{{ $active === '1' ? 'Có thể công bố' : 'Đang ẩn' }}</dd></div>
                </dl>
            </article>
        </aside>
        </div>
    </section>
    <script>
        (() => {
            const form = document.querySelector('[data-coupon-form]');
            if (!form) return;

            const type = form.querySelector('[name="discount_type"]');
            const cap = form.querySelector('[data-coupon-cap]');
            const capInput = form.querySelector('[name="max_discount_vnd"]');
            const discountInput = form.querySelector('[name="discount_value"]');
            const unit = form.querySelector('[data-coupon-unit]');
            const typeHelp = form.querySelector('[data-coupon-type-help]');
            const discountHelp = form.querySelector('[data-discount-help]');
            const warning = form.querySelector('[data-coupon-time-warning]');
            const codeInput = form.querySelector('[name="code"]');
            const activeInput = form.querySelector('[name="is_active"]');
            const minimumInput = form.querySelector('[name="min_subtotal_vnd"]');
            let selectedDiscountType = type?.value;
            const inputFor = (field) => form.querySelector(`[name="${field}"]`);
            const integer = (input) => Number.parseInt(input?.value || '0', 10) || 0;
            const money = (value) => `${new Intl.NumberFormat('vi-VN').format(value)}đ`;

            const syncPreview = () => {
                const percentage = type?.value === 'percentage';
                const discount = integer(discountInput);
                const minimum = integer(minimumInput);
                const maximum = integer(capInput);
                const enabled = activeInput?.value === '1';

                document.querySelector('[data-coupon-preview-code]').textContent = codeInput?.value.trim().toUpperCase() || 'CAFE15';
                document.querySelector('[data-coupon-preview-amount]').textContent = discount > 0
                    ? `Giảm ${percentage ? `${discount}%` : money(discount)}`
                    : 'Nhập mức giảm';
                document.querySelector('[data-coupon-preview-cap]').textContent = percentage && maximum > 0 ? `Tối đa ${money(maximum)}` : '';
                document.querySelector('[data-coupon-preview-minimum]').textContent = minimum > 0 ? `Đơn từ ${money(minimum)}` : 'Áp dụng cho mọi đơn';
                document.querySelector('[data-coupon-preview-type]').textContent = percentage ? 'Giảm theo phần trăm' : 'Giảm số tiền cố định';
                document.querySelector('[data-coupon-preview-status]').textContent = enabled ? 'Đang bật' : 'Đang tắt';
                document.querySelector('[data-coupon-preview-status]').dataset.tone = enabled ? 'green' : 'rose';
                document.querySelector('[data-coupon-preview-active]').textContent = enabled ? 'Có thể công bố' : 'Đang ẩn';
            };

            const refreshStepper = (stepper) => {
                const input = inputFor(stepper.dataset.field);
                if (!input) return;

                const percentage = type?.value === 'percentage';
                const minimum = Number(stepper.dataset.min || 0);
                const maximum = stepper.dataset.field === 'discount_value' && percentage
                    ? Number(stepper.dataset.maxPercentage || 100)
                    : Number.POSITIVE_INFINITY;
                const current = input.value === '' ? null : integer(input);

                stepper.querySelector('[data-coupon-step="-1"]').disabled = current === null || current <= minimum;
                stepper.querySelector('[data-coupon-step="1"]').disabled = input.disabled || (current !== null && current >= maximum);
            };

            const validateDates = () => {
                const now = new Date();
                now.setSeconds(0, 0);
                const currentLocal = new Date(now.getTime() - now.getTimezoneOffset() * 60000).toISOString().slice(0, 16);
                let hasPastValue = false;

                form.querySelectorAll('[data-coupon-date]').forEach((input) => {
                    const invalid = input.name === 'ends_at' && input.value && input.value < currentLocal;
                    input.setCustomValidity(invalid ? 'Không được chọn thời gian trong quá khứ.' : '');
                    hasPastValue ||= invalid;
                });

                warning.hidden = !hasPastValue;
            };

            const syncType = () => {
                const percentage = type?.value === 'percentage';
                cap.hidden = !percentage;
                capInput.disabled = !percentage;
                discountInput.max = percentage ? '100' : '';
                discountInput.step = '1';
                unit.textContent = percentage ? '%' : 'đ';
                typeHelp.textContent = percentage
                    ? 'Giảm theo tỷ lệ phần trăm. Có thể đặt thêm mức giảm tối đa.'
                    : 'Giảm trực tiếp một số tiền cố định. Không cần cấu hình mức giảm tối đa.';
                discountHelp.textContent = percentage ? 'Nhập từ 1 đến 100%.' : 'Số tiền phải lớn hơn 0đ.';
                form.querySelectorAll('[data-coupon-stepper]').forEach(refreshStepper);
                syncPreview();
            };

            form.querySelectorAll('[data-coupon-stepper]').forEach((stepper) => {
                const input = inputFor(stepper.dataset.field);

                stepper.querySelectorAll('[data-coupon-step]').forEach((button) => {
                    button.addEventListener('click', () => {
                        if (!input || input.disabled) return;

                        const percentage = type?.value === 'percentage';
                        const direction = Number(button.dataset.couponStep);
                        const step = stepper.dataset.field === 'discount_value'
                            ? Number(percentage ? stepper.dataset.stepPercentage : stepper.dataset.stepFixed)
                            : Number(stepper.dataset.step);
                        const minimum = Number(stepper.dataset.min || 0);
                        const maximum = stepper.dataset.field === 'discount_value' && percentage
                            ? Number(stepper.dataset.maxPercentage || 100)
                            : Number.POSITIVE_INFINITY;

                        input.value = String(Math.min(maximum, Math.max(minimum, integer(input) + direction * step)));
                        input.dispatchEvent(new Event('input', { bubbles: true }));
                    });
                });

                input?.addEventListener('input', () => {
                    refreshStepper(stepper);
                    syncPreview();
                });
                input?.addEventListener('change', () => {
                    if (input.value !== '' && integer(input) < Number(stepper.dataset.min || 0)) {
                        input.value = stepper.dataset.min || '0';
                    }
                    refreshStepper(stepper);
                });
            });

            type?.addEventListener('change', () => {
                if (type.value !== selectedDiscountType) {
                    discountInput.value = '';
                    capInput.value = '';
                    discountInput.setCustomValidity('');
                    capInput.setCustomValidity('');
                    selectedDiscountType = type.value;
                }

                syncType();
                discountInput.focus();
            });
            codeInput?.addEventListener('input', syncPreview);
            activeInput?.addEventListener('change', syncPreview);
            form.querySelectorAll('[data-coupon-date]').forEach((input) => {
                input.addEventListener('input', validateDates);
                input.addEventListener('change', validateDates);
            });
            form.addEventListener('submit', validateDates);
            syncType();
            validateDates();
        })();
    </script>
</x-layouts::admin>
