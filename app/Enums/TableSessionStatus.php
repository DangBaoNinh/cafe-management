<?php

namespace App\Enums;

enum TableSessionStatus: string
{
    case Open = 'open';
    case Closing = 'closing';
    case Paid = 'paid';
    case Closed = 'closed';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Đang mở',
            self::Closing => 'Đang chốt',
            self::Paid => 'Đã thanh toán',
            self::Closed => 'Đã đóng',
        };
    }
}
