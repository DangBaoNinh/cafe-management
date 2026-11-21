<?php

namespace App\Enums;

enum PaymentStatus: string
{
    case Pending = 'pending';
    case Paid = 'paid';
    case Voided = 'voided';
    case RefundPending = 'refund_pending';
    case Refunded = 'refunded';

    public function afterOrderReversal(): ?self
    {
        return match ($this) {
            self::Pending => self::Voided,
            self::Paid => self::RefundPending,
            default => null,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Chờ thanh toán',
            self::Paid => 'Đã thanh toán',
            self::Voided => 'Đã hủy thanh toán',
            self::RefundPending => 'Chờ hoàn tiền',
            self::Refunded => 'Đã hoàn tiền',
        };
    }
}
