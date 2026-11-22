<?php

namespace App\Models;

use App\Enums\ReservationStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property ReservationStatus $status
 * @property CarbonImmutable $scheduled_at
 * @property CarbonImmutable $arrival_deadline
 * @property CarbonImmutable|null $arrived_at
 * @property CarbonImmutable|null $completed_at
 * @property CarbonImmutable|null $no_show_at
 * @property array{bank_name: string, account_number: string, account_holder: string}|null $fee_bank_details
 */
#[Fillable([
    'reservation_seat_id', 'active_seat_id', 'code', 'booking_key', 'request_hash', 'contact_name',
    'phone', 'party_size', 'fee_channel', 'fee_bank_details', 'status', 'scheduled_at', 'arrival_deadline',
])]
class Reservation extends Model
{
    protected $attributes = ['revision' => 0];

    /** @return BelongsTo<ReservationSeat, $this> */
    public function seat(): BelongsTo
    {
        return $this->belongsTo(ReservationSeat::class, 'reservation_seat_id');
    }

    /** @return HasOne<ReservationDeposit, $this> */
    public function deposit(): HasOne
    {
        return $this->hasOne(ReservationDeposit::class);
    }

    /** @return HasMany<ReservationEvent, $this> */
    public function events(): HasMany
    {
        return $this->hasMany(ReservationEvent::class);
    }

    /** @return HasMany<PickupFee, $this> */
    public function fees(): HasMany
    {
        return $this->hasMany(PickupFee::class);
    }

    /** @return HasOne<TableSession, $this> */
    public function tableSession(): HasOne
    {
        return $this->hasOne(TableSession::class);
    }

    public function canChange(): bool
    {
        return $this->status === ReservationStatus::Confirmed && now()->lt($this->scheduled_at);
    }

    public function canCustomerCancel(): bool
    {
        return $this->status === ReservationStatus::Confirmed && now()->lte($this->created_at->copy()->addMinutes(30));
    }

    public function canSettleFees(): bool
    {
        return match ($this->status) {
            ReservationStatus::Confirmed => now()->lt($this->arrival_deadline),
            ReservationStatus::CheckedIn, ReservationStatus::Completed => true,
            default => false,
        };
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status' => ReservationStatus::class, 'scheduled_at' => 'immutable_datetime',
            'arrival_deadline' => 'immutable_datetime', 'arrived_at' => 'immutable_datetime',
            'completed_at' => 'immutable_datetime', 'no_show_at' => 'immutable_datetime',
            'fee_bank_details' => 'array',
        ];
    }
}
