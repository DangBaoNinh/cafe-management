<?php

namespace App\Http\Controllers;

use App\Enums\ReservationStatus;
use App\Models\ReservationSeat;
use App\Services\BookingRules;
use Illuminate\Http\JsonResponse;

class PublicSeatingController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $observedAt = now();
        $seats = ReservationSeat::query()
            ->with(['activeReservation:id,active_seat_id,status,scheduled_at', 'activeTableSession:id,active_seat_id'])
            ->orderBy('floor')->orderBy('position')->get();

        return response()->json([
            'observed_at' => $observedAt->toIso8601String(),
            'areas' => $seats->pluck('floor')->unique()->values()->map(fn ($floor): array => [
                'id' => (string) $floor, 'name' => 'Tầng '.$floor,
            ]),
            'tables' => $seats->map(function (ReservationSeat $seat) use ($observedAt): array {
                $status = $seat->activeTableSession !== null ? 'occupied' : match ($seat->availability()) {
                    'free' => 'available', 'held' => 'reserved', 'occupied' => 'occupied',
                    'inactive' => 'maintenance', default => 'unknown',
                };
                $reservation = $seat->activeReservation;
                $scheduledAt = $status === 'reserved' && $reservation?->status === ReservationStatus::Confirmed
                    && $reservation->scheduled_at->gt($observedAt) ? $reservation->scheduled_at : null;

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
