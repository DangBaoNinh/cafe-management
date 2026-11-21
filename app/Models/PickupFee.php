<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/** @property array{bank_name: string, account_number: string, account_holder: string}|null $bank_details */
#[Fillable([
    'revision', 'kind', 'amount_vnd', 'channel', 'old_slot', 'new_slot',
    'late_minutes', 'bank_details', 'transfer_code',
])]
class PickupFee extends Model
{
    public const CHANNELS = ['counter' => 'Tại quầy', 'app' => 'Qua ứng dụng'];

    public function statusLabel(): string
    {
        return match ($this->status) {
            'paid' => 'Đã thanh toán',
            'failed' => match ($this->transfer_result) {
                'expired' => 'Thất bại · Hết hạn',
                'cancelled' => 'Thất bại · Đã hủy giao dịch',
                default => 'Thất bại',
            },
            'voided' => 'Đã hủy khoản thu',
            'refund_pending' => 'Chờ hoàn tiền',
            'refunded' => 'Đã hoàn tiền',
            default => 'Chờ thanh toán',
        };
    }

    public function label(): string
    {
        return $this->kind === 'reschedule' ? 'Phí đổi lịch' : 'Phí đến trễ';
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'bank_details' => 'array',
            'old_slot' => 'immutable_datetime',
            'new_slot' => 'immutable_datetime',
        ];
    }
}
