<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case Cod = 'cod';
    case BankTransfer = 'bank_transfer';
    case DemoGateway = 'demo_gateway';

    public function label(): string
    {
        return match ($this) {
            self::Cod => 'Thanh toán khi nhận',
            self::BankTransfer => 'Chuyển khoản ngân hàng',
            self::DemoGateway => 'Chuyển khoản mô phỏng',
        };
    }
}
