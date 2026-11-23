<?php

namespace App\Services;

use App\Models\PickupFee;
use App\Models\Reservation;
use App\Models\ScheduledPickup;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Validation\ValidationException;

class BookingFees
{
    public function __construct(private Checkout $checkout) {}

    /** @param array<string, mixed> $details */
    public function add(ScheduledPickup|Reservation $booking, string $kind, int $amount, string $channel, array $details): void
    {
        $attributes = [
            'revision' => $booking->revision,
            'kind' => $kind,
            'amount_vnd' => $amount,
            'channel' => $channel,
        ] + $details;
        if ($channel === 'app') {
            $attributes['bank_details'] = ($booking instanceof Reservation ? $booking->fee_bank_details : null) ?? $this->onlineDetails();
            $attributes['transfer_code'] = 'PHI'.strtoupper(bin2hex(random_bytes(8)));
        }
        $booking->fees()->create($attributes);
    }

    /** @return array{bank_name: string, account_number: string, account_holder: string} */
    public function onlineDetails(): array
    {
        return $this->checkout->bankDetails()
            ?? throw ValidationException::withMessages(['channel' => 'Cửa hàng chưa cấu hình thu qua ứng dụng.']);
    }

    public function counterTotal(ScheduledPickup|Reservation $booking): int
    {
        return $booking->fees->where('channel', 'counter')->where('status', 'pending')->sum('amount_vnd');
    }

    public static function reverse(ScheduledPickup|Reservation $booking): void
    {
        $booking->fees()->whereIn('status', ['pending', 'failed'])->update(['status' => 'voided']);
        $booking->fees()->where('status', 'paid')->update(['status' => 'refund_pending']);
    }

    /** Called with the booking and fee locked. */
    public function resolve(User $actor, PickupFee $fee, bool $open, string $result, ?int $amount, ?string $memo, ?string $reference): void
    {
        if ($result === 'paid' && in_array($fee->status, ['paid', 'refund_pending', 'refunded'], true)
            && $fee->bank_reference === $reference && $fee->transfer_code === $memo && $fee->amount_vnd === $amount) {
            return;
        }
        $lateReceipt = $result === 'paid' && $fee->status === 'voided' && $fee->transfer_result === null;
        $this->require($fee->channel === 'app' && ($lateReceipt || ($open && $fee->status === 'pending')),
            'Khoản phí không còn chờ đối chiếu qua ứng dụng.');
        if ($result === 'paid') {
            $this->require($fee->amount_vnd === $amount && $fee->transfer_code === $memo && filled($reference),
                'Số tiền và nội dung chuyển khoản phải khớp. Nhập mã giao dịch trên sao kê.');
            $fee->bank_reference = $reference;
            $fee->paid_at = now();
        }
        $fee->status = match (true) {
            $lateReceipt => 'refund_pending',
            $result === 'paid' => 'paid',
            default => 'failed',
        };
        $fee->transfer_result = $result;
        $fee->transfer_resolved_at = now();
        $fee->transfer_actor_id = $actor->id;
        try {
            $fee->save();
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['fee' => 'Mã giao dịch này đã được dùng cho một khoản phí.']);
        }
    }

    /** Called with the booking and fee locked. */
    public function moveToCounter(User $actor, PickupFee $fee, bool $open): void
    {
        $this->require($open && $fee->channel === 'app' && $fee->status === 'failed'
            && in_array($fee->transfer_result, ['failed', 'expired', 'cancelled'], true),
            'Chỉ chuyển sang quầy khi giao dịch qua ứng dụng đã thất bại, hết hạn hoặc bị hủy.');
        $fee->channel = 'counter';
        $fee->status = 'pending';
        $fee->moved_to_counter_at = now();
        $fee->counter_actor_id = $actor->id;
        $fee->save();
    }

    /** Called with the booking and fee locked. */
    public function refund(User $actor, PickupFee $fee, string $reference): void
    {
        $this->require($fee->status === 'refund_pending', 'Khoản phí không chờ hoàn tiền.');
        $fee->status = 'refunded';
        $fee->refund_reference = $reference;
        $fee->refunded_at = now();
        $fee->refund_actor_id = $actor->id;
        try {
            $fee->save();
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['fee' => 'Mã hoàn tiền này đã được dùng cho một khoản phí.']);
        }
    }

    private function require(bool $allowed, string $message): void
    {
        if (! $allowed) {
            throw ValidationException::withMessages(['fee' => $message]);
        }
    }
}
