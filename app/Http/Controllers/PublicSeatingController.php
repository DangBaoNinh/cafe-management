<?php

namespace App\Http\Controllers;

use App\Models\ReservationSeat;
use Illuminate\Http\JsonResponse;

class PublicSeatingController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $seats = ReservationSeat::query()
            ->with(['activeReservation:id,active_seat_id,status', 'activeTableSession:id,active_seat_id'])
            ->orderBy('floor')->orderBy('position')->get();

        return response()->json([
            'observed_at' => now()->toIso8601String(),
            'areas' => $seats->pluck('floor')->unique()->values()->map(fn ($floor): array => [
                'id' => (string) $floor, 'name' => 'Tầng '.$floor,
            ]),
            'tables' => $seats->map(fn (ReservationSeat $seat): array => [
                'id' => (string) $seat->id,
                'code' => $seat->code,
                'area_id' => (string) $seat->floor,
                'capacity' => $seat->capacity,
                'status' => $seat->activeTableSession !== null ? 'occupied' : match ($seat->availability()) {
                    'free' => 'available', 'held' => 'reserved', 'occupied' => 'occupied',
                    'inactive' => 'maintenance', default => 'unknown',
                },
            ]),
        ])->header('Cache-Control', 'no-store, private');
    }
}
