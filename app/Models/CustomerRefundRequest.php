<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property Order|null $order
 * @property ReservationDeposit|null $deposit
 * @property User|null $completedBy
 */
#[Guarded([])]
class CustomerRefundRequest extends Model
{
    /** @return BelongsTo<User, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /** @return BelongsTo<ReservationDeposit, $this> */
    public function deposit(): BelongsTo
    {
        return $this->belongsTo(ReservationDeposit::class, 'reservation_deposit_id');
    }

    /** @return BelongsTo<User, $this> */
    public function completedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }

    protected function casts(): array
    {
        return ['is_demo' => 'boolean', 'completed_at' => 'immutable_datetime', 'bank_snapshot' => 'encrypted:array', 'amount_vnd' => 'integer'];
    }
}
