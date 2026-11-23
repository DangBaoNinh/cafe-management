<?php

namespace App\Services;

use App\Enums\ReservationStatus;
use App\Models\Reservation;
use App\Models\ReservationDeposit;
use App\Models\ReservationSeat;
use App\Models\TableInvoice;
use App\Models\TableSession;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ReservationDeposits
{
    public const AMOUNT = 100000;

    public function create(Reservation $reservation): ReservationDeposit
    {
        $bank = app(BankTransferQr::class)->details();
        $this->require($bank !== null, 'Chưa cấu hình ngân hàng nhận cọc.');
        $deposit = $reservation->deposit()->create(['reference' => (string) Str::uuid(), 'amount_vnd' => $reservation->seat->kind === 'room' && $reservation->seat->capacity >= 10 ? 300000 : self::AMOUNT, 'bank_details' => $bank]);
        $deposit->update(['number' => 'DC'.str_pad((string) $deposit->id, 7, '0', STR_PAD_LEFT)]);

        return $deposit;
    }

    public function demoPay(User $customer, Reservation $target): void
    {
        Gate::forUser($customer)->authorize('access-customer-orders');
        abort_unless(config('checkout.reservation_deposit_demo'), 403, 'Chức năng xác nhận cọc demo đang tắt.');
        abort_unless($target->user_id === $customer->id, 404);
        DB::transaction(function () use ($customer, $target): void {
            User::query()->lockForUpdate()->findOrFail($customer->id);
            $seat = ReservationSeat::query()->lockForUpdate()->findOrFail($target->reservation_seat_id);
            $reservation = Reservation::query()->lockForUpdate()->findOrFail($target->id);
            $deposit = $reservation->deposit()->lockForUpdate()->firstOrFail();
            if (in_array($deposit->status, ['paid', 'settled'], true)) {
                return;
            }
            $this->require($reservation->status === ReservationStatus::PendingDeposit && $deposit->status === 'pending', 'Lượt đặt không còn chờ cọc.');
            $this->require(now()->lt($reservation->arrival_deadline), 'Lượt đặt đã quá hạn nhận bàn. Hãy chọn giờ mới.');
            $this->require(ReservationCalendar::available($seat, $reservation->scheduled_at, $reservation->id), 'Bàn vừa được đặt hoặc đang sử dụng. Vui lòng chọn bàn khác.');
            $deposit->update(['status' => 'paid', 'demo' => true, 'paid_at' => now()]);
            $reservation->update(['status' => ReservationStatus::Confirmed, 'active_seat_id' => $reservation->scheduled_at->setTimezone(BookingRules::TIMEZONE)->isToday() ? $seat->id : null]);
            $reservation->events()->create(['actor_id' => $customer->id, 'from_status' => ReservationStatus::PendingDeposit,
                'to_status' => ReservationStatus::Confirmed, 'occurred_at' => now(), 'reason' => 'Đã xác nhận cọc chuyển khoản demo.']);
        }, 5);
    }

    /** Called after locking the table session. A draft claims the deposit, but does not settle it. */
    public function claim(TableInvoice $invoice): void
    {
        $reservationId = $invoice->tableSession->reservation_id;
        if ($reservationId === null) {
            return;
        }
        $deposit = ReservationDeposit::query()->where('reservation_id', $reservationId)->where('status', 'paid')
            ->where(fn ($q) => $q->whereNull('table_invoice_id')->orWhere('table_invoice_id', $invoice->id))->lockForUpdate()->first();
        if ($deposit === null) {
            return;
        }
        $deposit->update(['table_invoice_id' => $invoice->id]);
        $invoice->update(['deposit_credit_vnd' => min($deposit->amount_vnd, $invoice->total_vnd),
            'deposit_refund_vnd' => max(0, $deposit->amount_vnd - $invoice->total_vnd)]);
    }

    public function settle(TableInvoice $invoice, User $actor, bool $refundConfirmed): void
    {
        $deposit = ReservationDeposit::query()->where('table_invoice_id', $invoice->id)->lockForUpdate()->first();
        if ($deposit === null) {
            $this->require($invoice->deposit_credit_vnd === 0 && $invoice->deposit_refund_vnd === 0, 'Khoản cọc của hóa đơn đã thay đổi.');

            return;
        }
        $this->require($deposit->status === 'paid' && $invoice->deposit_credit_vnd === min($deposit->amount_vnd, $invoice->total_vnd)
            && $invoice->deposit_refund_vnd === max(0, $deposit->amount_vnd - $invoice->total_vnd), 'Khoản cọc đã được dùng hoặc có thay đổi.');
        $this->require($invoice->deposit_refund_vnd === 0 || $refundConfirmed, 'Xác nhận đã hoàn tiền mặt phần cọc dư trước khi hoàn tất.');
        $deposit->update(['status' => 'settled', 'credited_vnd' => $invoice->deposit_credit_vnd,
            'cash_refund_vnd' => $invoice->deposit_refund_vnd, 'settled_at' => now(), 'settled_by' => $actor->id]);
    }

    public function available(TableSession $session, ?TableInvoice $invoice): int
    {
        return (int) ReservationDeposit::query()->where('reservation_id', $session->reservation_id)->where('status', 'paid')
            ->where(fn ($q) => $q->whereNull('table_invoice_id')->when($invoice !== null, fn ($q) => $q->orWhere('table_invoice_id', $invoice->id)))
            ->value('amount_vnd');
    }

    /** Called while the reservation is locked when it is cancelled or marked no-show. */
    public function forfeit(Reservation $reservation, ?string $reason = null): void
    {
        $deposit = $reservation->deposit()->lockForUpdate()->first();
        if ($deposit?->status === 'paid') {
            $deposit->update(['status' => 'forfeited', 'settled_at' => now(), 'forfeit_reason' => $reason ?? 'Khách không đến nhận bàn đúng hạn.']);
        } elseif ($deposit?->status === 'pending') {
            $deposit->update(['status' => 'voided']);
        }
    }

    private function require(bool $condition, string $message): void
    {
        if (! $condition) {
            throw ValidationException::withMessages(['deposit' => $message])->status(409);
        }
    }
}
