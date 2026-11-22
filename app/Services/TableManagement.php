<?php

namespace App\Services;

use App\Enums\ReservationStatus;
use App\Models\ReservationSeat;
use App\Models\TableSession;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class TableManagement
{
    public function __construct(private TableSessions $orders, private TableReservations $reservations) {}

    public function openForOrder(User $actor, ReservationSeat $target): TableSession
    {
        Gate::forUser($actor)->authorize('operate-orders');

        return DB::transaction(function () use ($actor, $target): TableSession {
            $seat = ReservationSeat::query()->lockForUpdate()->findOrFail($target->id);
            $current = $seat->activeTableSession()->first();

            return $current ?? $this->orders->open($actor, $seat);
        }, 5);
    }

    public function changeStatus(User $actor, ReservationSeat $target, string $expected, string $status): void
    {
        Gate::forUser($actor)->authorize('operate-orders');
        if ($status === 'inactive') {
            Gate::forUser($actor)->authorize('access-admin');
        }
        DB::transaction(function () use ($actor, $target, $expected, $status): void {
            $seat = ReservationSeat::query()->lockForUpdate()->findOrFail($target->id);
            $current = $seat->availability();
            $this->require($current === $expected, 'Trạng thái bàn đã thay đổi. Vui lòng tải lại danh sách.');
            if ($status === $current) {
                return;
            }
            if ($status === 'inactive') {
                $this->require($current === 'free', 'Chỉ ngừng phục vụ khi bàn đang trống.');
                $this->reservations->setSeatActive($actor, $seat->id, false);
            } elseif ($status === 'occupied') {
                $this->require(in_array($current, ['free', 'held'], true), 'Bàn đang ngừng phục vụ.');
                $this->reservations->walkIn($actor, $seat->id, true);
            } elseif ($status === 'free') {
                if ($current === 'inactive') {
                    Gate::forUser($actor)->authorize('access-admin');
                    $this->reservations->setSeatActive($actor, $seat->id, true);

                    return;
                }
                $this->require($current === 'occupied', 'Bàn đang được đặt trước. Hãy xử lý trên lịch đặt bàn.');
                $orderContext = $seat->activeTableSession()->first();
                if ($orderContext !== null) {
                    $this->orders->close($actor, $orderContext);
                } elseif ($seat->activeReservation?->status === ReservationStatus::CheckedIn) {
                    $this->reservations->release($actor, $seat->activeReservation->code);
                } else {
                    $this->reservations->walkIn($actor, $seat->id, false);
                }
            }
        }, 5);
    }

    private function require(bool $valid, string $message): void
    {
        if (! $valid) {
            throw ValidationException::withMessages(['table_status' => $message])->status(409);
        }
    }
}
