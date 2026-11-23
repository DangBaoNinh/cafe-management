<?php

namespace App\Services;

use App\Enums\ReservationStatus;
use App\Models\Reservation;
use App\Models\ReservationSeat;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

class ReservationCalendar
{
    /** @return Builder<Reservation> */
    public static function bookings(int $seatId, CarbonImmutable $day): Builder
    {
        $start = $day->setTimezone(BookingRules::TIMEZONE)->startOfDay()->utc();

        return Reservation::query()->where('reservation_seat_id', $seatId)
            ->whereIn('status', [ReservationStatus::Confirmed, ReservationStatus::CheckedIn, ReservationStatus::Completed])
            ->where('scheduled_at', '>=', $start)->where('scheduled_at', '<', $start->addDay());
    }

    public static function available(ReservationSeat $seat, CarbonImmutable $day, ?int $except = null): bool
    {
        $today = $day->setTimezone(BookingRules::TIMEZONE)->isToday();

        return $seat->is_active && (! $today || ($seat->walk_in_at === null && ! $seat->activeTableSession()->exists() && ! $seat->activeReservation()->where('status', ReservationStatus::CheckedIn)->exists()))
            && ! self::bookings($seat->id, $day)->when($except, fn ($q) => $q->whereKeyNot($except))->exists();
    }
}
