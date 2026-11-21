<?php

namespace App\Services;

use App\Enums\PaymentMethod;
use App\Models\Order;
use App\Models\PickupFee;
use App\Models\ScheduledPickup;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class PickupFees
{
    public function __construct(private BookingFees $fees) {}

    /** @param array<string, mixed> $details */
    public function add(ScheduledPickup $pickup, string $kind, int $amount, string $channel, array $details): void
    {
        $this->fees->add($pickup, $kind, $amount, $channel, $details);
    }

    /** @return array{items: int, fees: int, total: int, waiting: bool} */
    public function invoice(Order $order, ScheduledPickup $pickup): array
    {
        $items = $order->payment?->method === PaymentMethod::Cod ? $order->total_vnd : 0;
        $fees = $this->fees->counterTotal($pickup);
        $waiting = $pickup->fees->where('channel', 'app')->contains(fn (PickupFee $fee): bool => $fee->status !== 'paid');

        return ['items' => $items, 'fees' => $fees, 'total' => $items + $fees, 'waiting' => $waiting];
    }

    public function resolve(User $actor, string $code, int $feeId, string $result, ?int $amount, ?string $memo, ?string $reference): void
    {
        Gate::forUser($actor)->authorize('operate-orders');

        DB::transaction(function () use ($actor, $code, $feeId, $result, $amount, $memo, $reference): void {
            [$order, $pickup, $fee] = $this->lock($code, $feeId);
            $this->fees->resolve($actor, $fee, $pickup->isOpen($order), $result, $amount, $memo, $reference);
        }, 5);
    }

    public function moveToCounter(User $actor, string $code, int $feeId): void
    {
        Gate::forUser($actor)->authorize('operate-orders');

        DB::transaction(function () use ($actor, $code, $feeId): void {
            [$order, $pickup, $fee] = $this->lock($code, $feeId);
            $this->fees->moveToCounter($actor, $fee, $pickup->isOpen($order));
        }, 5);
    }

    public function refund(User $actor, string $code, int $feeId, string $reference): void
    {
        Gate::forUser($actor)->authorize('access-admin');

        DB::transaction(function () use ($actor, $code, $feeId, $reference): void {
            [, , $fee] = $this->lock($code, $feeId);
            $this->fees->refund($actor, $fee, $reference);
        }, 5);
    }

    /** Called under the order lock when the order is cancelled or rejected. */
    public static function reverse(Order $order): void
    {
        $pickup = $order->scheduledPickup()->first();
        if ($pickup === null) {
            return;
        }
        BookingFees::reverse($pickup);
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
}
