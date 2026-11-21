<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\ReservationStatus;
use Illuminate\Support\Facades\DB;

class BookingExpirations
{
    public function __construct(private ScheduledPickups $pickups, private TableReservations $reservations) {}

    /** @return array{pickups:int, reservations:int} */
    public function run(): array
    {
        $now = now();
        $pickups = DB::table('scheduled_pickups')->join('orders', 'orders.id', '=', 'scheduled_pickups.order_id')
            ->whereNull('scheduled_pickups.arrived_at')->whereNull('scheduled_pickups.no_show_at')
            ->whereNotIn('orders.status', OrderStatus::finishedValues())->where('arrival_deadline', '<=', $now)
            ->selectRaw('scheduled_pickups.id, arrival_deadline, 0 as source');
        $reservations = DB::table('reservations')->where('status', ReservationStatus::Confirmed->value)
            ->where('arrival_deadline', '<=', $now)->selectRaw('id, arrival_deadline, 1 as source');
        $due = DB::query()->fromSub($pickups->unionAll($reservations), 'due_bookings')
            ->orderBy('arrival_deadline')->orderBy('source')->orderBy('id');

        $counts = ['pickups' => 0, 'reservations' => 0];
        $last = null;
        do {
            $query = clone $due;
            if ($last !== null) {
                $query->whereRaw('(arrival_deadline, source, id) > (?, ?, ?)', [$last->arrival_deadline, $last->source, $last->id]);
            }
            $batch = $query->limit(100)->get();
            foreach ($batch as $candidate) {
                if ((int) $candidate->source === 0) {
                    $counts['pickups'] += (int) $this->pickups->expireCandidate((int) $candidate->id, $now);
                } else {
                    $counts['reservations'] += (int) $this->reservations->expire((int) $candidate->id, $now);
                }
            }
            $last = $batch->last();
        } while ($batch->count() === 100);

        return $counts;
    }
}
