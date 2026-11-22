<?php

namespace App\Services;

use App\Enums\ReservationStatus;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class ReservationFees
{
    public function __construct(private BookingFees $fees) {}

    public function resolve(User $actor, string $code, int $feeId, string $result, ?int $amount, ?string $memo, ?string $reference): void
    {
        Gate::forUser($actor)->authorize('operate-orders');
        DB::transaction(function () use ($actor, $code, $feeId, $result, $amount, $memo, $reference): void {
            $reservation = $this->lock($code);
            $fee = $reservation->fees()->lockForUpdate()->findOrFail($feeId);
            $this->fees->resolve($actor, $fee, $reservation->canSettleFees(), $result, $amount, $memo, $reference);
        }, 5);
    }

    public function moveToCounter(User $actor, string $code, int $feeId): void
    {
        Gate::forUser($actor)->authorize('operate-orders');
        DB::transaction(function () use ($actor, $code, $feeId): void {
            $reservation = $this->lock($code);
            if ($reservation->tableSession()->whereNotNull('billed_at')->exists()) {
                throw ValidationException::withMessages(['fee' => 'Hóa đơn tại bàn đã chốt. Khoản phí này không thể chuyển sang quầy.']);
            }
            $fee = $reservation->fees()->lockForUpdate()->findOrFail($feeId);
            $this->fees->moveToCounter($actor, $fee, $reservation->canSettleFees());
        }, 5);
    }

    public function refund(User $actor, string $code, int $feeId, string $reference): void
    {
        Gate::forUser($actor)->authorize('access-admin');
        DB::transaction(function () use ($actor, $code, $feeId, $reference): void {
            $reservation = $this->lock($code);
            $fee = $reservation->fees()->lockForUpdate()->findOrFail($feeId);
            $this->fees->refund($actor, $fee, $reference);
        }, 5);
    }

    public function collect(User $actor, string $code, int $amount): void
    {
        Gate::forUser($actor)->authorize('operate-orders');
        DB::transaction(function () use ($actor, $code, $amount): void {
            $reservation = $this->lock($code);
            if ($reservation->tableSession()->exists()) {
                throw ValidationException::withMessages(['amount_vnd' => 'Hãy thu phụ phí qua hóa đơn của phiên gọi món.']);
            }
            $total = $this->fees->counterTotal($reservation);
            if (! in_array($reservation->status, [ReservationStatus::CheckedIn, ReservationStatus::Completed], true)
                || $total === 0 || $amount !== $total) {
                throw ValidationException::withMessages(['amount_vnd' => 'Số tiền đã thu phải khớp số còn phải trả tại quầy sau check-in.']);
            }
            $reservation->fees()->where('channel', 'counter')->where('status', 'pending')->update([
                'status' => 'paid', 'paid_at' => now(), 'counter_actor_id' => $actor->id,
            ]);
        }, 5);
    }

    private function lock(string $code): Reservation
    {
        return Reservation::query()->where('code', $code)->lockForUpdate()->firstOrFail();
    }
}
