<?php

namespace App\Enums;

enum InventoryMovementKind: string
{
    case Receipt = 'receipt';
    case Count = 'count';
    case Adjustment = 'adjustment';
    case Consumption = 'consumption';

    public function label(): string
    {
        return match ($this) {
            self::Receipt => 'Nhập kho',
            self::Count => 'Kiểm kê',
            self::Adjustment => 'Điều chỉnh',
            self::Consumption => 'Tiêu hao pha chế',
        };
    }
}
