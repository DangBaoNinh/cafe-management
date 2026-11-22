<?php

namespace App\Services;

use App\Enums\ReservationStatus;
use App\Models\Reservation;
use App\Models\ReservationHours;
use App\Models\ReservationSeat;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class TableReservations
{
    public function __construct(private BookingFees $fees) {}

    /** @param array{seat_id:int, kind:string, party_size:int, pickup_time:string, contact_name:string, phone:string, channel:string, booking_key:string} $input */
    public function place(User $customer, array $input): Reservation
    {
        $hash = hash('sha256', json_encode($input, JSON_THROW_ON_ERROR));

        return DB::transaction(function () use ($customer, $input, $hash): Reservation {
            $customer = User::query()->lockForUpdate()->findOrFail($customer->id);
            Gate::forUser($customer)->authorize('access-customer-orders');
            $existing = $customer->reservations()->where('booking_key', $input['booking_key'])->first();
            if ($existing !== null) {
                if (! hash_equals($existing->request_hash, $hash)) {
                    throw ValidationException::withMessages(['booking_key' => 'Mã xác nhận đã dùng cho nội dung đặt chỗ khác.'])->status(409);
                }

                return $existing;
            }

            BookingRules::authorizeBooking($customer);
            $slot = BookingRules::slot($input['pickup_time']);
            $seat = ReservationSeat::query()->lockForUpdate()->findOrFail($input['seat_id']);
            $this->require($seat->is_active && $seat->kind === $input['kind'] && $seat->capacity >= $input['party_size'],
                'Chỗ đã ngừng phục vụ hoặc không phù hợp với loại chỗ và số khách.');
            $this->require($seat->walk_in_at === null && $seat->activeReservation()->lockForUpdate()->first() === null,
                'Chỗ vừa được giữ hoặc đang sử dụng. Vui lòng chọn lại.');
            ReservationHours::query()->sharedLock()->sole()->validateSlot($slot);

            $reservation = $customer->reservations()->create([
                'reservation_seat_id' => $seat->id, 'active_seat_id' => $seat->id,
                'code' => strtoupper(bin2hex(random_bytes(8))), 'booking_key' => $input['booking_key'], 'request_hash' => $hash,
                'contact_name' => $input['contact_name'], 'phone' => $input['phone'], 'party_size' => $input['party_size'],
                'fee_channel' => $input['channel'],
                'fee_bank_details' => $input['channel'] === 'app' ? $this->fees->onlineDetails() : null,
                'status' => ReservationStatus::Confirmed,
                'scheduled_at' => $slot, 'arrival_deadline' => $slot->addMinutes(30),
            ]);
            $this->record($reservation, null, $customer, now());

            return $reservation;
        }, 5);
    }

    public function reschedule(User $customer, string $code, string $input, int $revision): void
    {
        $target = $customer->reservations()->where('code', $code)->firstOrFail();
        DB::transaction(function () use ($target, $input, $revision): void {
            [$customer, $seat, $reservation] = $this->lock($target);
            Gate::forUser($customer)->authorize('access-customer-orders');
            BookingRules::authorizeBooking($customer);
            $this->require($reservation->canChange(), 'Chỉ đổi lịch còn hiệu lực trước giờ hẹn.');
            $this->require($reservation->revision === $revision, 'Lịch đã thay đổi. Vui lòng tải lại trang.');
            $this->require($seat->is_active && $seat->walk_in_at === null && $reservation->active_seat_id === $seat->id,
                'Chỗ đã thay đổi tình trạng. Vui lòng kiểm tra lịch.');
            $previous = $reservation->scheduled_at;
            $slot = BookingRules::replacementSlot($input, $previous);
            ReservationHours::query()->sharedLock()->sole()->validateSlot($slot);
            $reservation->scheduled_at = $slot;
            $reservation->arrival_deadline = $slot->addMinutes(30);
            $reservation->revision++;
            $reservation->save();
            $this->fees->add($reservation, 'reschedule', BookingRules::CHANGE_FEE, $reservation->fee_channel,
                ['old_slot' => $previous, 'new_slot' => $slot]);
        }, 5);
    }

    public function cancel(User $actor, string $code, ?string $reason = null): void
    {
        $staff = $reason !== null;
        Gate::forUser($actor)->authorize($staff ? 'operate-orders' : 'access-customer-orders');
        $target = Reservation::query()->where('code', $code)->when(! $staff, fn ($query) => $query->where('user_id', $actor->id))->firstOrFail();
        DB::transaction(function () use ($target, $actor, $staff, $reason): void {
            [, , $reservation] = $this->lock($target);
            $this->require($staff
                ? $reservation->status === ReservationStatus::Confirmed && now()->lt($reservation->arrival_deadline)
                : $reservation->canChange(), 'Lịch không còn đủ điều kiện hủy.');
            $this->finish($reservation, ReservationStatus::Cancelled, now(), $actor, $reason);
            BookingFees::reverse($reservation);
        }, 5);
    }

    public function checkIn(User $staff, string $code): void
    {
        Gate::forUser($staff)->authorize('operate-orders');
        $target = Reservation::query()->where('code', $code)->firstOrFail();
        DB::transaction(function () use ($target, $staff): void {
            [$customer, , $reservation] = $this->lock($target);
            $arrival = now();
            $this->require($reservation->status === ReservationStatus::Confirmed
                && $arrival->gte($reservation->scheduled_at) && $arrival->lt($reservation->arrival_deadline),
                'Chỉ check-in lịch đang giữ từ giờ hẹn đến trước hạn 30 phút.');
            $late = BookingRules::lateMinutes($reservation->scheduled_at, $arrival);
            if ($late > 5) {
                $this->fees->add($reservation, 'late', ($late - 5) * 2000, $reservation->fee_channel, ['late_minutes' => $late]);
            }
            $reservation->arrived_at = $arrival;
            $reservation->status = ReservationStatus::CheckedIn;
            $reservation->save();
            $this->record($reservation, ReservationStatus::Confirmed, $staff, $arrival);
            BookingRules::recordAttendance($customer, true, $arrival);
        }, 5);
    }

    public function release(User $staff, string $code): void
    {
        Gate::forUser($staff)->authorize('operate-orders');
        $target = Reservation::query()->where('code', $code)->firstOrFail();
        DB::transaction(function () use ($target, $staff): void {
            [, $seat, $reservation] = $this->lock($target);
            $this->require(! $seat->activeTableSession()->exists(), 'Hãy đóng phiên gọi món để trả chỗ.');
            $this->releaseCheckedIn($reservation, $staff);
        }, 5);
    }

    /** Called with the seat and reservation locked, after any table session is closed. */
    public function releaseCheckedIn(Reservation $reservation, User $staff): void
    {
        $this->require($reservation->status === ReservationStatus::CheckedIn, 'Chỉ trả chỗ sau khi đã check-in.');
        $reservation->completed_at = now();
        $this->finish($reservation, ReservationStatus::Completed, $reservation->completed_at, $staff);
    }

    public function expire(int $id, CarbonImmutable $asOf): bool
    {
        $target = Reservation::query()->findOrFail($id);

        return DB::transaction(function () use ($target, $asOf): bool {
            [$customer, , $reservation] = $this->lock($target);
            if ($reservation->status !== ReservationStatus::Confirmed || $reservation->arrival_deadline->gt($asOf)) {
                return false;
            }
            $reservation->no_show_at = $reservation->arrival_deadline;
            $this->finish($reservation, ReservationStatus::NoShow, $reservation->arrival_deadline, reason: 'Khách không đến trong 30 phút sau giờ hẹn.');
            BookingFees::reverse($reservation);
            BookingRules::recordAttendance($customer, false, $reservation->arrival_deadline);

            return true;
        }, 5);
    }

    public function setSeatActive(User $admin, int $seatId, bool $active): void
    {
        Gate::forUser($admin)->authorize('access-admin');
        DB::transaction(function () use ($seatId, $active): void {
            $seat = ReservationSeat::query()->lockForUpdate()->findOrFail($seatId);
            $this->require($active || ($seat->walk_in_at === null && $seat->activeReservation()->lockForUpdate()->first() === null),
                'Chỉ ngừng phục vụ khi chỗ đang trống.');
            $seat->is_active = $active;
            $seat->save();
        }, 5);
    }

    public function updateHours(User $admin, string $first, string $last): void
    {
        Gate::forUser($admin)->authorize('access-admin');
        $this->require(in_array($first, BookingRules::times(), true) && in_array($last, BookingRules::times(), true) && $first <= $last,
            'Giờ nhận đặt chỗ phải theo bước 5 phút, trong khoảng 07:00–22:30 và đúng thứ tự.');
        DB::transaction(function () use ($first, $last): void {
            $hours = ReservationHours::query()->lockForUpdate()->sole();
            $hours->first_slot = $first;
            $hours->last_slot = $last;
            $hours->save();
        }, 5);
    }

    public function walkIn(User $staff, int $seatId, bool $arriving): void
    {
        Gate::forUser($staff)->authorize('operate-orders');
        DB::transaction(function () use ($staff, $seatId, $arriving): void {
            $seat = ReservationSeat::query()->lockForUpdate()->findOrFail($seatId);
            if ($arriving) {
                $this->require($seat->is_active && $seat->walk_in_at === null && $seat->activeReservation()->lockForUpdate()->first() === null,
                    'Chỉ ghi khách trực tiếp vào chỗ đang trống.');
                $seat->walk_in_at = now();
                $seat->walk_in_actor_id = $staff->id;
            } else {
                $this->require($seat->walk_in_at !== null, 'Chỗ này không có khách đến trực tiếp.');
                $this->require(! $seat->activeTableSession()->exists(), 'Hãy đóng phiên gọi món để trả chỗ.');
                $seat->walk_in_at = null;
                $seat->walk_out_at = now();
                $seat->walk_out_actor_id = $staff->id;
            }
            $seat->save();
        }, 5);
    }

    /** @return array{User, ReservationSeat, Reservation} */
    private function lock(Reservation $target): array
    {
        // The customer lock also serializes attendance with UC26. The seat lock guards every occupancy change.
        $customer = User::query()->lockForUpdate()->findOrFail($target->user_id);
        $seat = ReservationSeat::query()->lockForUpdate()->findOrFail($target->reservation_seat_id);
        $reservation = Reservation::query()->lockForUpdate()->findOrFail($target->id);

        return [$customer, $seat, $reservation];
    }

    private function finish(Reservation $reservation, ReservationStatus $status, CarbonImmutable $at, ?User $actor = null, ?string $reason = null): void
    {
        $previous = $reservation->status;
        $reservation->status = $status;
        $reservation->active_seat_id = null;
        $reservation->save();
        $this->record($reservation, $previous, $actor, $at, $reason);
    }

    private function record(Reservation $reservation, ?ReservationStatus $previous, ?User $actor, CarbonImmutable $at, ?string $reason = null): void
    {
        $reservation->events()->create([
            'actor_id' => $actor?->id, 'from_status' => $previous?->value, 'to_status' => $reservation->status->value,
            'occurred_at' => $at, 'reason' => $reason,
        ]);
    }

    private function require(bool $condition, string $message): void
    {
        if (! $condition) {
            throw ValidationException::withMessages(['reservation' => $message]);
        }
    }
}
