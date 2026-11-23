<?php

namespace App\Enums;

enum CounterOrderStatus: string
{
    case PendingPayment = 'pending_payment';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::PendingPayment => 'Chờ thanh toán',
            self::Completed => 'Hoàn tất',
            self::Cancelled => 'Bị hủy',
        };
    }
}
