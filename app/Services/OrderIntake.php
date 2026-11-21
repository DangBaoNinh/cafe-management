<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class OrderIntake
{
    public function decide(User $actor, string $number, OrderStatus $decision, ?string $reason): void
    {
        Gate::forUser($actor)->authorize('operate-orders');
        if (! match ($decision) {
            OrderStatus::Confirmed => $reason === null,
            OrderStatus::Rejected => $reason !== null && trim($reason) !== '',
            default => false,
        }) {
            throw new InvalidArgumentException('Invalid order intake decision or reason.');
        }

        DB::transaction(function () use ($actor, $number, $decision, $reason): void {
            // Serialize decisions with other order writers, including customer cancellation.
            $order = Order::query()->where('order_number', $number)->lockForUpdate()->firstOrFail();
            if ($order->status !== OrderStatus::Pending) {
                throw $this->conflict($number, 'Đơn đã đổi trạng thái. Vui lòng tải lại trang để xem trạng thái hiện tại.');
            }
            if ($order->scheduledPickup?->hasMissedArrival()) {
                throw $this->conflict($number, 'Đơn hẹn đã quá hạn nhận và đang chờ hủy do không đến.');
            }

            $payment = $order->payment()->lockForUpdate()->first();
            $previousPaymentStatus = $payment?->status;
            $reversedPaymentStatus = $payment?->status->afterOrderReversal($payment->amount_vnd);
            if ($payment === null || $previousPaymentStatus === null || $reversedPaymentStatus === null) {
                throw $this->conflict($number, 'Vui lòng kiểm tra trạng thái thanh toán trước khi xử lý đơn.');
            }

            $order->update(['status' => $decision]);
            $order->statusHistories()->create([
                'actor_id' => $actor->id,
                'from_status' => OrderStatus::Pending->value,
                'to_status' => $decision->value,
                'reason' => $reason,
            ]);

            if ($decision === OrderStatus::Rejected) {
                $payment->update(['status' => $reversedPaymentStatus]);
                $payment->statusHistories()->create([
                    'actor_id' => $actor->id,
                    'from_status' => $previousPaymentStatus->value,
                    'to_status' => $reversedPaymentStatus->value,
                    'reason' => $reason,
                ]);
                PickupFees::reverse($order);
            }
        }, 5);
    }

    private function conflict(string $number, string $message): ValidationException
    {
        return ValidationException::withMessages(['expected_status' => $message])
            ->status(409)->redirectTo(route('operations.orders.show', $number));
    }
}
