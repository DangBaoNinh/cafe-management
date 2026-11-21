<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CancelCustomerOrder
{
    public function cancel(User $customer, string $number, ?string $reason): void
    {
        DB::transaction(function () use ($customer, $number, $reason): void {
            // Order writers must lock the order before locking its payment.
            $order = $customer->orders()->where('order_number', $number)->lockForUpdate()->firstOrFail();
            if ($order->status !== OrderStatus::Pending) {
                throw ValidationException::withMessages([
                    'expected_status' => 'Đơn đã đổi trạng thái. Vui lòng tải lại trang để xem trạng thái hiện tại.',
                ])->status(409)->redirectTo(route('orders.show', $number));
            }

            $payment = $order->payment()->lockForUpdate()->first();
            $previousPaymentStatus = $payment?->status;
            $nextPaymentStatus = $previousPaymentStatus?->afterOrderReversal();
            if ($payment === null || $previousPaymentStatus === null || $nextPaymentStatus === null) {
                throw ValidationException::withMessages([
                    'expected_status' => 'Không thể hủy đơn với trạng thái thanh toán hiện tại. Vui lòng liên hệ cửa hàng.',
                ])->status(409)->redirectTo(route('orders.show', $number));
            }

            $order->update(['status' => OrderStatus::Cancelled]);
            $payment->update(['status' => $nextPaymentStatus]);
            $order->statusHistories()->create([
                'actor_id' => $customer->id,
                'from_status' => OrderStatus::Pending->value,
                'to_status' => OrderStatus::Cancelled->value,
                'reason' => $reason,
            ]);
            $payment->statusHistories()->create([
                'actor_id' => $customer->id,
                'from_status' => $previousPaymentStatus->value,
                'to_status' => $nextPaymentStatus->value,
                'reason' => $reason,
            ]);
        }, 5);
    }
}
