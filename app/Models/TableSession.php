<?php

namespace App\Models;

use App\Enums\TableSessionStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property TableSessionStatus $status
 * @property CarbonImmutable|null $billed_at
 * @property CarbonImmutable|null $paid_at
 */
#[Fillable(['reference', 'reservation_seat_id', 'active_seat_id', 'reservation_id', 'opened_by', 'access_code', 'access_digest', 'status', 'billing_mode'])]
#[Hidden(['access_code', 'access_digest'])]
class TableSession extends Model
{
    public function getRouteKeyName(): string
    {
        return 'reference';
    }

    /** @return BelongsTo<ReservationSeat, $this> */
    public function seat(): BelongsTo
    {
        return $this->belongsTo(ReservationSeat::class, 'reservation_seat_id');
    }

    /** @return BelongsTo<Reservation, $this> */
    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
    }

    /** @return HasMany<TableRound, $this> */
    public function rounds(): HasMany
    {
        return $this->hasMany(TableRound::class);
    }

    /** @return HasMany<TableInvoice, $this> */
    public function invoices(): HasMany
    {
        return $this->hasMany(TableInvoice::class);
    }

    /** @return HasMany<PickupFee, $this> */
    public function fees(): HasMany
    {
        return $this->hasMany(PickupFee::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status' => TableSessionStatus::class, 'access_code' => 'encrypted',
            'checkout_requested_at' => 'immutable_datetime', 'billed_at' => 'immutable_datetime',
            'locked_at' => 'immutable_datetime', 'paid_at' => 'immutable_datetime', 'closed_at' => 'immutable_datetime',
            'subtotal_vnd' => 'integer', 'fees_vnd' => 'integer', 'total_vnd' => 'integer',
        ];
    }
}
