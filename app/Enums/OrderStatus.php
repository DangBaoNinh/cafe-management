<?php

namespace App\Enums;

enum OrderStatus: string
{
    case Pending = 'pending';
    case Confirmed = 'confirmed';
    case Preparing = 'preparing';
    case Ready = 'ready';
    case OutForDelivery = 'out_for_delivery';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
    case Rejected = 'rejected';

    /** @return list<string> */
    public static function finishedValues(): array
    {
        return [self::Completed->value, self::Cancelled->value, self::Rejected->value];
    }

    /** @return list<string> */
    public static function transferReconciliationValues(): array
    {
        return [self::Pending->value, self::Confirmed->value];
    }

    public function allowsAdminCancellation(): bool
    {
        return in_array($this, [self::Pending, self::Confirmed, self::Preparing, self::Ready], true);
    }

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Chờ xác nhận',
            self::Confirmed => 'Đã xác nhận',
            self::Preparing => 'Đang pha chế',
            self::Ready => 'Đã chuẩn bị xong',
            self::OutForDelivery => 'Đang giao',
            self::Completed => 'Đã hoàn tất',
            self::Cancelled => 'Đã hủy',
            self::Rejected => 'Đã từ chối',
        };
    }
}
