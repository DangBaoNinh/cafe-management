<?php

namespace App\Http\Controllers;

use App\Enums\ReservationStatus;
use App\Models\ReservationSeat;
use App\Services\BookingRules;
use App\Services\ReservationCalendar;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PublicSeatingController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $data = $request->validate(['date' => ['nullable', 'date_format:Y-m-d']]);
        $day = CarbonImmutable::parse($data['date'] ?? now(BookingRules::TIMEZONE)->toDateString(), BookingRules::TIMEZONE);
        $observedAt = now();
        $seats = ReservationSeat::query()
            ->with(['activeReservation:id,reservation_seat_id,active_seat_id,status,scheduled_at', 'activeTableSession:id,active_seat_id'])
            ->orderBy('floor')->orderBy('position')->get();

        return response()->json([
            'observed_at' => $observedAt->toIso8601String(),
            'areas' => $seats->pluck('floor')->unique()->values()->map(fn ($floor): array => [
                'id' => (string) $floor, 'name' => 'Tầng '.$floor,
            ]),
            'tables' => $seats->map(function (ReservationSeat $seat) use ($day): array {
                $booking = ReservationCalendar::bookings($seat->id, $day)->first();
                $seat->setRelation('activeReservation', $booking);
                if (! $day->isToday()) {
                    $seat->walk_in_at = null;
                    $seat->setRelation('activeTableSession', null);
                }
                $status = $seat->activeTableSession !== null ? 'occupied' : match ($seat->availability()) {
                    'free' => 'available', 'held' => 'reserved', 'occupied' => 'occupied',
                    'inactive' => 'maintenance', default => 'unknown',
                };
                $reservation = $seat->activeReservation;
                $scheduledAt = $status === 'reserved' && $reservation?->status === ReservationStatus::Confirmed
                    ? $reservation->scheduled_at : null;

                return [
                    'id' => (string) $seat->id,
                    'code' => $seat->code,
                    'area_id' => (string) $seat->floor,
                    'capacity' => $seat->capacity,
                    'status' => $status,
                    'scheduled_at' => $scheduledAt?->setTimezone(BookingRules::TIMEZONE)->format('Y-m-d\TH:iP'),
                ];
            }),
        ])->header('Cache-Control', 'no-store, private');
    }
}
