<?php

namespace App\Services;

use App\Enums\ReservationStatus;
use App\Enums\TableRoundStatus;
use App\Enums\TableSessionStatus;
use App\Models\ReservationSeat;
use App\Models\TableSession;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class TableSessions
{
    public function __construct(private TableReservations $reservations) {}

    public function open(User $actor, ReservationSeat $target): TableSession
    {
        Gate::forUser($actor)->authorize('operate-orders');

        return DB::transaction(function () use ($actor, $target): TableSession {
            $seat = ReservationSeat::query()->lockForUpdate()->findOrFail($target->id);
            $this->require($seat->is_active && ! $seat->activeTableSession()->exists(), 'Chỗ này đã có phiên gọi món hoặc đang ngừng phục vụ.');
            $reservation = $seat->activeReservation()->lockForUpdate()->first();
            $this->require($reservation === null || $reservation->status === ReservationStatus::CheckedIn, 'Hãy check-in lượt đặt trước khi mở phiên.');
            if ($reservation === null && $seat->walk_in_at === null) {
                $this->reservations->walkIn($actor, $seat->id, true);
            }

            // The digest is globally unique so a code from another seat cannot match by coincidence.
            for ($attempt = 0; $attempt < 5; $attempt++) {
                $pin = str_pad((string) random_int(0, 99_999_999), 8, '0', STR_PAD_LEFT);
                try {
                    return TableSession::create([
                        'reference' => Str::uuid()->toString(), 'reservation_seat_id' => $seat->id, 'active_seat_id' => $seat->id,
                        'reservation_id' => $reservation?->id, 'opened_by' => $actor->id, 'access_code' => $pin,
                        'access_digest' => $this->digest($pin), 'status' => TableSessionStatus::Open,
                        'billing_mode' => 'prepaid',
                    ]);
                } catch (UniqueConstraintViolationException $exception) {
                    if ($attempt === 4) {
                        throw $exception;
                    }
                }
            }

            throw new \LogicException('Session code generation ended without returning or throwing.');
        }, 5);
    }

    public function join(ReservationSeat $seat, string $code): TableSession
    {
        $session = $seat->activeTableSession()->first();
        if ($session === null || $session->status !== TableSessionStatus::Open || ! hash_equals($session->access_digest, $this->digest($code))) {
            throw ValidationException::withMessages(['access_code' => 'Mã phiên không hợp lệ hoặc phiên đã ngừng nhận món.']);
        }

        return $session;
    }

    public function requestCheckout(TableSession $target): void
    {
        DB::transaction(function () use ($target): void {
            $session = $this->lock($target);
            $this->require($session->billing_mode !== 'prepaid', 'Hãy mở tạm tính của từng order để thanh toán ngay.');
            $this->require($session->status === TableSessionStatus::Open, 'Phiên này đang được tính tiền hoặc đã kết thúc.');
            if ($session->checkout_requested_at === null) {
                $session->checkout_requested_at = now();
                $session->save();
            }
        }, 5);
    }

    public function lockOrdering(User $actor, TableSession $target): void
    {
        Gate::forUser($actor)->authorize('operate-orders');
        DB::transaction(function () use ($actor, $target): void {
            $session = $this->lock($target);
            $this->require($session->billing_mode !== 'prepaid', 'Phiên trả trước thanh toán theo từng order; quản lý trả chỗ riêng.');
            $this->require($session->status === TableSessionStatus::Open, 'Chỉ khóa gọi thêm khi phiên đang mở.');
            $session->status = TableSessionStatus::Closing;
            $session->locked_by = $actor->id;
            $session->locked_at = now();
            $session->save();
        }, 5);
    }

    public function bill(User $actor, TableSession $target): void
    {
        Gate::forUser($actor)->authorize('operate-orders');
        DB::transaction(function () use ($actor, $target): void {
            $session = $this->lock($target);
            $this->require($session->status === TableSessionStatus::Closing, 'Hãy khóa gọi thêm trước khi chốt hóa đơn.');
            if ($session->billed_at !== null) {
                return;
            }
            $this->require(! $session->rounds()->whereIn('status', TableRoundStatus::unfinished())->exists(), 'Còn lượt chưa xử lý. Hãy phục vụ, từ chối hoặc hủy lượt trước khi chốt.');
            $reservation = $session->reservation()->lockForUpdate()->first();
            if ($reservation !== null) {
                $onlinePending = $reservation->fees()->where('channel', 'app')->where('status', 'pending')->lockForUpdate()->first();
                $this->require($onlinePending === null, 'Còn phụ phí qua ứng dụng chưa đối chiếu. Hãy xử lý trên lượt đặt chỗ trước khi chốt.');
                $fees = $reservation->fees()->where('channel', 'counter')->where('status', 'pending')->lockForUpdate()->get();
                $reservation->fees()->whereKey($fees->modelKeys())->update(['table_session_id' => $session->id]);
                $session->fees_vnd = $fees->sum('amount_vnd');
            } else {
                $session->fees_vnd = 0;
            }
            $subtotal = filter_var($session->rounds()->where('status', TableRoundStatus::Served)->sum('total_vnd'), FILTER_VALIDATE_INT);
            if ($subtotal === false || $subtotal < 0 || $subtotal > PHP_INT_MAX - $session->fees_vnd) {
                throw ValidationException::withMessages(['table_session' => 'Tổng hóa đơn vượt giới hạn VND được hỗ trợ.']);
            }
            $session->subtotal_vnd = $subtotal;
            $session->total_vnd = $session->subtotal_vnd + $session->fees_vnd;
            $session->billed_by = $actor->id;
            $session->billed_at = now();
            $session->save();
        }, 5);
    }

    public function pay(User $actor, TableSession $target, int $amount): void
    {
        Gate::forUser($actor)->authorize('operate-orders');
        DB::transaction(function () use ($actor, $target, $amount): void {
            $session = $this->lock($target);
            $this->require($session->billed_at !== null && $session->total_vnd === $amount, 'Số tiền thu phải khớp tổng hóa đơn đã chốt.');
            if ($session->paid_at !== null) {
                return;
            }
            $this->require($session->status === TableSessionStatus::Closing, 'Phiên chưa chốt hóa đơn.');
            $session->reservation()->lockForUpdate()->first();
            $paidAt = now();
            $session->fees()->where('status', 'pending')->update(['status' => 'paid', 'paid_at' => $paidAt, 'counter_actor_id' => $actor->id]);
            $session->status = TableSessionStatus::Paid;
            $session->paid_by = $actor->id;
            $session->paid_at = $paidAt;
            $session->save();
        }, 5);
    }

    public function close(User $actor, TableSession $target): void
    {
        Gate::forUser($actor)->authorize('operate-orders');
        DB::transaction(function () use ($actor, $target): void {
            $seat = ReservationSeat::query()->lockForUpdate()->findOrFail($target->reservation_seat_id);
            $session = $this->lock($target);
            if ($session->status === TableSessionStatus::Closed) {
                return;
            }
            $this->require($session->billing_mode === 'prepaid' || $session->status === TableSessionStatus::Paid, 'Chỉ trả chỗ sau khi thu tiền và xác nhận khách rời đi.');
            $reservation = $session->reservation()->lockForUpdate()->first();
            $session->status = TableSessionStatus::Closed;
            $session->active_seat_id = null;
            $session->access_code = null;
            $session->closed_by = $actor->id;
            $session->closed_at = now();
            $session->save();

            if ($reservation !== null) {
                $this->reservations->releaseCheckedIn($reservation, $actor);
            } else {
                $this->reservations->walkIn($actor, $seat->id, false);
            }
        }, 5);
    }

    private function lock(TableSession $target): TableSession
    {
        return TableSession::query()->lockForUpdate()->findOrFail($target->id);
    }

    private function digest(string $code): string
    {
        return hash_hmac('sha256', $code, (string) config('app.key'));
    }

    private function require(bool $allowed, string $message): void
    {
        if (! $allowed) {
            throw ValidationException::withMessages(['table_session' => $message])->status(409);
        }
    }
}
