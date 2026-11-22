<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Guarded([])]
class ReservationDeposit extends Model
{
    protected $attributes = ['status' => 'pending', 'amount_vnd' => 100000, 'demo' => false, 'credited_vnd' => 0, 'cash_refund_vnd' => 0];

    public function getRouteKeyName(): string
    {
        return 'reference';
    }

    /** @return BelongsTo<Reservation, $this> */
    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
    }

    /** @return BelongsTo<TableInvoice, $this> */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(TableInvoice::class, 'table_invoice_id');
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            'paid' => 'Đã nhận cọc', 'settled' => 'Đã hoàn — đã trừ vào hóa đơn', 'forfeited' => 'Đã giữ cọc — hủy/không đến',
            'refund_pending' => 'Chờ hoàn tiền', 'refunded' => 'Đã hoàn tiền vào tài khoản', 'voided' => 'Đã hủy', default => 'Chờ chuyển khoản cọc',
        };
    }

    protected function casts(): array
    {
        return ['bank_details' => 'array', 'amount_vnd' => 'integer', 'credited_vnd' => 'integer', 'cash_refund_vnd' => 'integer',
            'demo' => 'boolean', 'paid_at' => 'immutable_datetime', 'settled_at' => 'immutable_datetime'];
    }
}
