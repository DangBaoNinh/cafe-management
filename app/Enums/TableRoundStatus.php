<?php

namespace App\Enums;

enum TableRoundStatus: string
{
    case Pending = 'pending';
    case Accepted = 'accepted';
    case Preparing = 'preparing';
    case Ready = 'ready';
    case Served = 'served';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Chờ tiếp nhận',
            self::Accepted => 'Đã tiếp nhận',
            self::Preparing => 'Đang pha',
            self::Ready => 'Sẵn sàng',
            self::Served => 'Đã phục vụ',
            self::Rejected => 'Đã từ chối',
            self::Cancelled => 'Đã hủy',
        };
    }

    /** @return list<self> */
    public function next(): array
    {
        return match ($this) {
            self::Pending => [self::Accepted, self::Rejected, self::Cancelled],
            self::Accepted => [self::Preparing, self::Cancelled],
            self::Preparing => [self::Ready, self::Cancelled],
            self::Ready => [self::Served, self::Cancelled],
            default => [],
        };
    }

    public function needsReason(): bool
    {
        return in_array($this, [self::Rejected, self::Cancelled], true);
    }

    /** @return list<self> */
    public static function unfinished(): array
    {
        return [self::Pending, self::Accepted, self::Preparing, self::Ready];
    }
}
