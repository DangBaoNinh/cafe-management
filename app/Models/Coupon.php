<?php

namespace App\Models;

use App\Enums\CouponDiscountType;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/** @property CouponDiscountType $discount_type */
#[Fillable([
    'code', 'discount_type', 'discount_value', 'min_subtotal_vnd', 'max_discount_vnd',
    'starts_at', 'ends_at', 'total_limit', 'per_user_limit', 'is_active',
])]
class Coupon extends Model
{
    public const LOCAL_TIMEZONE = 'Asia/Ho_Chi_Minh';

    public const FORM_DATE_FORMAT = 'Y-m-d\TH:i';

    protected $attributes = [
        'discount_type' => CouponDiscountType::Fixed->value,
        'min_subtotal_vnd' => 0,
        'is_active' => true,
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'discount_type' => CouponDiscountType::class,
            'discount_value' => 'integer',
            'min_subtotal_vnd' => 'integer',
            'max_discount_vnd' => 'integer',
            'starts_at' => 'immutable_datetime',
            'ends_at' => 'immutable_datetime',
            'total_limit' => 'integer',
            'per_user_limit' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public static function normalizeCode(string $code): string
    {
        return strtoupper(trim($code));
    }

    public function isAvailableAt(DateTimeInterface $at): bool
    {
        return $this->is_active && $this->starts_at <= $at && $at < $this->ends_at;
    }

    /**
     * Usage counts are for orders that still consume a redemption under BR10.
     * Checkout supplies both counts after locking the coupon in its order transaction.
     * A null result means the coupon is ineligible; zero is a valid rounded discount.
     */
    public function discountFor(int $subtotal, int $totalUses, int $customerUses, DateTimeInterface $at): ?int
    {
        if (
            ! $this->isAvailableAt($at)
            || $subtotal < $this->min_subtotal_vnd
            || ($this->total_limit !== null && $totalUses >= $this->total_limit)
            || ($this->per_user_limit !== null && $customerUses >= $this->per_user_limit)
        ) {
            return null;
        }

        if ($this->discount_type === CouponDiscountType::Fixed) {
            return min($subtotal, $this->discount_value);
        }

        // Divide first so the percentage calculation stays in the integer range.
        $discount = intdiv($subtotal, 100) * $this->discount_value
            + intdiv(($subtotal % 100) * $this->discount_value, 100);

        return min($subtotal, $discount, $this->max_discount_vnd ?? $subtotal);
    }
}
