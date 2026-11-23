<?php

namespace App\Enums;

enum ReservationStatus: string
{
    case PendingDeposit = 'pending_deposit';
    case Confirmed = 'confirmed';
    case CheckedIn = 'checked_in';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
    case NoShow = 'no_show';

    public function label(): string
    {
        return match ($this) {
            self::PendingDeposit => 'Chờ đặt cọc',
            self::Confirmed => 'Đã đặt chỗ',
            self::CheckedIn => 'Đang sử dụng',
            self::Completed => 'Đã trả chỗ',
            self::Cancelled => 'Đã hủy',
            self::NoShow => 'Không đến',
        };
    }
}
