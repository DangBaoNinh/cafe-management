<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\User;

class OrderCancellation
{
    /**
     * Call inside the transaction after locking the order and authorizing the cancellation.
     * A false result leaves the order and payment untouched.
     */
    public function record(Order $order, User $actor, ?string $reason): bool
    {
        $payment = $order->payment()->lockForUpdate()->first();
        $previousPaymentStatus = $payment?->status;
        $nextPaymentStatus = $payment?->status->afterOrderReversal($payment->amount_vnd);
        if ($payment === null || $previousPaymentStatus === null || $nextPaymentStatus === null) {
            return false;
        }

        $previousOrderStatus = $order->status;
        $order->update(['status' => OrderStatus::Cancelled]);
        $payment->update(['status' => $nextPaymentStatus]);
        $order->statusHistories()->create([
            'actor_id' => $actor->id,
            'from_status' => $previousOrderStatus->value,
            'to_status' => OrderStatus::Cancelled->value,
            'reason' => $reason,
        ]);
        $payment->statusHistories()->create([
            'actor_id' => $actor->id,
            'from_status' => $previousPaymentStatus->value,
            'to_status' => $nextPaymentStatus->value,
            'reason' => $reason,
        ]);

        return true;
    }
}
