<?php

namespace App\Enums;

enum CouponDiscountType: string
{
    case Fixed = 'fixed';
    case Percentage = 'percentage';

    public function label(): string
    {
        return match ($this) {
            self::Fixed => 'Số tiền cố định',
            self::Percentage => 'Phần trăm',
        };
    }
}
