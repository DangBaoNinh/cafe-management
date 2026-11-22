<?php

namespace App\Models;

use App\Enums\ReservationStatus;
use App\Services\BookingRules;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ReservationSeat extends Model
{
    /** @return HasManyThrough<TableInvoice, TableSession, $this> */
    public function tableInvoices(): HasManyThrough
    {
        return $this->hasManyThrough(TableInvoice::class, TableSession::class, 'reservation_seat_id', 'table_session_id');
    }

    public const KINDS = ['table' => 'Bàn', 'room' => 'Phòng họp'];

    public $timestamps = false;

    /** @return HasOne<Reservation, $this> */
    public function activeReservation(): HasOne
    {
        return $this->hasOne(Reservation::class, 'reservation_seat_id')->where(function ($q): void {
            $start = now(BookingRules::TIMEZONE)->startOfDay()->utc();
            $q->where('status', ReservationStatus::CheckedIn)->orWhere(function ($q) use ($start): void {
                $q->where('status', ReservationStatus::Confirmed)->where('scheduled_at', '>=', $start)->where('scheduled_at', '<', $start->copy()->addDay());
            });
        });
    }

    /** @return HasOne<TableSession, $this> */
    public function activeTableSession(): HasOne
    {
        return $this->hasOne(TableSession::class, 'active_seat_id');
    }

    public function availability(): string
    {
        return match (true) {
            $this->walk_in_at !== null || $this->activeReservation?->status === ReservationStatus::CheckedIn => 'occupied',
            $this->activeReservation !== null => 'held',
            ! $this->is_active => 'inactive',
            default => 'free',
        };
    }

    public function availabilityLabel(): string
    {
        return match ($this->availability()) {
            'occupied' => 'Đang sử dụng',
            'held' => 'Đã đặt',
            'inactive' => 'Ngừng phục vụ',
            default => 'Trống',
        };
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
