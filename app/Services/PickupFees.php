<?php

namespace App\Services;

use App\Enums\PaymentMethod;
use App\Models\Order;
use App\Models\PickupFee;
use App\Models\ScheduledPickup;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class PickupFees
{
    public function __construct(private Checkout $checkout) {}

    /** @param array<string, mixed> $details */
    public function add(ScheduledPickup $pickup, string $kind, int $amount, string $channel, array $details): void
    {
        $attributes = [
            'revision' => $pickup->revision,
            'kind' => $kind,
            'amount_vnd' => $amount,
            'channel' => $channel,
        ] + $details;
        if ($channel === 'app') {
            $attributes['bank_details'] = $this->checkout->bankDetails()
                ?? throw ValidationException::withMessages(['channel' => 'Cửa hàng chưa cấu hình thu qua ứng dụng.']);
            $attributes['transfer_code'] = 'PHI'.strtoupper(bin2hex(random_bytes(8)));
        }
        $pickup->fees()->create($attributes);
    }

    /** @return array{items: int, fees: int, total: int, waiting: bool} */
    public function invoice(Order $order, ScheduledPickup $pickup): array
    {
        $items = $order->payment?->method === PaymentMethod::Cod ? $order->total_vnd : 0;
        $fees = $pickup->fees->where('channel', 'counter')->where('status', 'pending')->sum('amount_vnd');
        $waiting = $pickup->fees->where('channel', 'app')->contains(fn (PickupFee $fee): bool => $fee->status !== 'paid');

        return ['items' => $items, 'fees' => $fees, 'total' => $items + $fees, 'waiting' => $waiting];
    }

    public function resolve(User $actor, string $code, int $feeId, string $result, ?int $amount, ?string $memo, ?string $reference): void
    {
        Gate::forUser($actor)->authorize('operate-orders');

        DB::transaction(function () use ($actor, $code, $feeId, $result, $amount, $memo, $reference): void {
            [$order, $pickup, $fee] = $this->lock($code, $feeId);
            if ($result === 'paid' && in_array($fee->status, ['paid', 'refund_pending', 'refunded'], true)
                && $fee->bank_reference === $reference && $fee->transfer_code === $memo && $fee->amount_vnd === $amount) {
                return;
            }
            $lateReceipt = $result === 'paid' && $fee->status === 'voided' && $fee->transfer_result === null;
            $this->require($fee->channel === 'app' && ($lateReceipt || ($pickup->isOpen($order) && $fee->status === 'pending')),
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
        }, 5);
    }

    public function moveToCounter(User $actor, string $code, int $feeId): void
    {
        Gate::forUser($actor)->authorize('operate-orders');

        DB::transaction(function () use ($actor, $code, $feeId): void {
            [$order, $pickup, $fee] = $this->lock($code, $feeId);
            $this->require($pickup->isOpen($order) && $fee->channel === 'app' && $fee->status === 'failed'
                && in_array($fee->transfer_result, ['failed', 'expired', 'cancelled'], true),
                'Chỉ chuyển sang quầy khi giao dịch qua ứng dụng đã thất bại, hết hạn hoặc bị hủy.');
            $fee->channel = 'counter';
            $fee->status = 'pending';
            $fee->moved_to_counter_at = now();
            $fee->counter_actor_id = $actor->id;
            $fee->save();
        }, 5);
    }

    public function refund(User $actor, string $code, int $feeId, string $reference): void
    {
        Gate::forUser($actor)->authorize('access-admin');

        DB::transaction(function () use ($actor, $code, $feeId, $reference): void {
            [, , $fee] = $this->lock($code, $feeId);
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
        }, 5);
    }

    /** Called under the order lock when the order is cancelled or rejected. */
    public static function reverse(Order $order): void
    {
        $pickup = $order->scheduledPickup()->first();
        if ($pickup === null) {
            return;
        }
        $pickup->fees()->whereIn('status', ['pending', 'failed'])->update(['status' => 'voided']);
        $pickup->fees()->where('status', 'paid')->update(['status' => 'refund_pending']);
    }

    /** @return array{Order, ScheduledPickup, PickupFee} */
    private function lock(string $code, int $feeId): array
    {
        $pickup = ScheduledPickup::query()->where('code', $code)->firstOrFail();
        $order = $pickup->order()->lockForUpdate()->firstOrFail();
        $pickup = $order->scheduledPickup()->lockForUpdate()->firstOrFail();
        $fee = $pickup->fees()->lockForUpdate()->findOrFail($feeId);

        return [$order, $pickup, $fee];
    }

    private function require(bool $allowed, string $message): void
    {
        if (! $allowed) {
            throw ValidationException::withMessages(['fee' => $message]);
        }
    }
}
