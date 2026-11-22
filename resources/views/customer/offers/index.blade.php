@php
    $money = static fn (int $amount): string => number_format($amount, 0, ',', '.').' đ';
@endphp

<x-layouts::customer title="Ưu đãi">
    <section class="french-offers space-y-10">
        <header class="french-offers__header">
            <p class="eyebrow">Les privilèges</p>
            <h1>Ưu đãi trong ngày</h1>
            <p>Chọn một phiếu phù hợp, sao chép mã và nhập ở bước đặt hàng. Điều kiện và giá trị giảm được kiểm tra lại khi chốt đơn.</p>
        </header>

        @if ($coupons->isEmpty())
            <div class="french-offers__empty" role="status">
                <p class="eyebrow">Hôm nay</p>
                <h2>Chưa có ưu đãi đang hiệu lực</h2>
                <p>Thực đơn vẫn sẵn sàng để bạn chọn món yêu thích.</p>
                <a href="{{ route('menu.index') }}" class="jardin-button">Xem thực đơn</a>
            </div>
        @else
            <ul class="french-offers__grid" aria-label="Danh sách ưu đãi đang hiệu lực">
                @foreach ($coupons as $coupon)
                    @php($percentage = $coupon->discount_type === \App\Enums\CouponDiscountType::Percentage)
                    <li class="french-offer-card">
                        <div class="french-offer-card__top">
                            <p class="eyebrow">Phiếu ưu đãi</p>
                            <span aria-label="Đang hiệu lực">✦</span>
                        </div>
                        <p class="french-offer-card__value">
                            {{ $percentage ? 'Giảm '.$coupon->discount_value.'%' : 'Giảm '.$money($coupon->discount_value) }}
                        </p>
                        <p class="french-offer-card__condition">
                            @if ($percentage && $coupon->max_discount_vnd !== null)
                                Tối đa {{ $money($coupon->max_discount_vnd) }}
                            @elseif ($coupon->min_subtotal_vnd > 0)
                                Cho đơn từ {{ $money($coupon->min_subtotal_vnd) }}
                            @else
                                Áp dụng theo điều kiện của cửa hàng
                            @endif
                        </p>
                        <div class="french-offer-card__code">
                            <span>{{ $coupon->code }}</span>
                            <button type="button" data-copy-coupon data-coupon-code="{{ $coupon->code }}" aria-label="Sao chép mã {{ $coupon->code }}">Sao chép</button>
                        </div>
                        <dl class="french-offer-card__details">
                            <div><dt>Đơn tối thiểu</dt><dd>{{ $coupon->min_subtotal_vnd > 0 ? $money($coupon->min_subtotal_vnd) : 'Không yêu cầu' }}</dd></div>
                            <div><dt>Dùng đến</dt><dd>{{ $coupon->ends_at->setTimezone(\App\Models\Coupon::LOCAL_TIMEZONE)->format('d/m/Y H:i') }}</dd></div>
                        </dl>
                        <a href="{{ route('menu.index') }}" class="french-offer-card__action">Chọn món để dùng mã <span aria-hidden="true">→</span></a>
                    </li>
                @endforeach
            </ul>

            {{ $coupons->onEachSide(1)->links() }}
        @endif
    </section>

    <script>
        document.querySelectorAll('[data-copy-coupon]').forEach((button) => {
            button.addEventListener('click', async () => {
                const original = button.textContent;
                try {
                    await navigator.clipboard.writeText(button.dataset.couponCode);
                    button.textContent = 'Đã sao chép';
                } catch (_) {
                    button.textContent = 'Không sao chép được';
                }
                window.setTimeout(() => { button.textContent = original; }, 1800);
            });
        });
    </script>
</x-layouts::customer>
