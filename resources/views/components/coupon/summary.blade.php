@props(['coupon', 'now'])

@php
    $percentage = $coupon->discount_type === \App\Enums\CouponDiscountType::Percentage;
    $status = match (true) {
        ! $coupon->is_active => ['Đã tắt', 'rose'],
        $coupon->isAvailableAt($now) => ['Đang hiệu lực', 'green'],
        $now < $coupon->starts_at => ['Chưa bắt đầu', 'blue'],
        default => ['Đã hết hạn', 'amber'],
    };
    $format = static fn (int $amount): string => number_format($amount, 0, ',', '.').' ₫';
@endphp

<article {{ $attributes->class(['coupon-summary']) }} aria-label="Mã giảm giá {{ $coupon->code }}">
    <div class="coupon-summary__seal" aria-hidden="true">ƯĐ</div>
    <div class="min-w-0 space-y-1">
        <p class="coupon-summary__eyebrow">Phiếu ưu đãi</p>
        <h2 class="coupon-summary__code">{{ $coupon->code }}</h2>
    </div>
    <span class="terrasse-pill coupon-summary__status" data-tone="{{ $status[1] }}">{{ $status[0] }}</span>

    <div class="coupon-summary__offer">
        <p class="coupon-summary__amount">
            @if ($percentage)
                Giảm {{ $coupon->discount_value }}%
            @else
                Giảm {{ $format($coupon->discount_value) }}
            @endif
        </p>
        <p class="terrasse-muted text-sm">
            @if ($percentage && $coupon->max_discount_vnd !== null)
                Tối đa {{ $format($coupon->max_discount_vnd) }}
            @elseif ($coupon->min_subtotal_vnd > 0)
                Đơn từ {{ $format($coupon->min_subtotal_vnd) }}
            @else
                Áp dụng theo điều kiện cửa hàng
            @endif
        </p>
    </div>

    <dl class="coupon-summary__details">
        <div><dt>Điều kiện</dt><dd>Đơn từ {{ $format($coupon->min_subtotal_vnd) }}</dd></div>
        <div><dt>Thời hạn</dt><dd>{{ $coupon->ends_at->setTimezone(\App\Models\Coupon::LOCAL_TIMEZONE)->format('d/m/Y H:i') }}</dd></div>
        <div><dt>Lượt dùng</dt><dd>{{ $coupon->total_limit === null ? 'Không giới hạn' : $coupon->total_limit.' lượt tổng' }}</dd></div>
    </dl>

    {{ $slot }}
</article>
