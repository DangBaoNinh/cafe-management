<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\CustomerRefundRequest;
use App\Models\Order;
use App\Models\ReservationDeposit;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class CustomerRefunds
{
    public static function refundLateDemoReceipt(Order $order): void
    {
        if ($order->status !== OrderStatus::Cancelled || ! DemoPaymentGateway::enabled() || ! config('checkout.customer_refund_demo', true)) {
            return;
        }
        $actorId = $order->statusHistories()->where('to_status', 'cancelled')->latest('id')->value('actor_id');
        $owner = User::find($order->user_id);
        if ($owner === null || (int) $actorId !== $owner->id || empty($owner->linked_bank['account_number'])) {
            return;
        }
        $payment = $order->payment()->firstOrFail();
        if ($payment->status === PaymentStatus::RefundPending) {
            self::request($owner, $payment->amount_vnd, orderId: $order->id);
        }
    }

    public static function requireBank(User $user): void
    {
        if (! is_array($user->linked_bank) || empty($user->linked_bank['account_number'])) {
            throw ValidationException::withMessages(['refund' => 'Vui lòng liên kết tài khoản ngân hàng trong Hồ sơ trước khi hủy.']);
        }
    }

    public static function request(User $user, int $amount, ?int $depositId = null, ?int $orderId = null): void
    {
        self::requireBank($user);
        $refund = CustomerRefundRequest::firstOrCreate(['reservation_deposit_id' => $depositId, 'order_id' => $orderId], [
            'user_id' => $user->id, 'amount_vnd' => $amount, 'bank_snapshot' => $user->linked_bank, 'status' => 'pending',
        ]);
        if (! DemoPaymentGateway::enabled() || ! config('checkout.customer_refund_demo', true) || $refund->status !== 'pending') {
            return;
        }
        $reference = 'DEMO-REFUND-'.$refund->id;
        if ($depositId !== null) {
            ReservationDeposit::query()->whereKey($depositId)->where('status', 'refund_pending')->update(['status' => 'refunded']);
        }
        if ($orderId !== null) {
            $payment = Order::findOrFail($orderId)->payment()->lockForUpdate()->firstOrFail();
            if ($payment->status !== PaymentStatus::RefundPending) {
                throw ValidationException::withMessages(['refund' => 'Thanh toán không còn chờ hoàn tiền.']);
            }
            $payment->forceFill(['status' => PaymentStatus::Refunded, 'refund_reference' => $reference, 'refunded_at' => now()])->save();
            $payment->statusHistories()->create(['actor_id' => $user->id, 'from_status' => 'refund_pending', 'to_status' => 'refunded',
                'reason' => 'Tự động hoàn tiền demo vào ngân hàng liên kết. '.$reference]);
        }
        $refund->update(['status' => 'completed', 'is_demo' => true, 'refund_reference' => $reference, 'completed_at' => now()]);
    }
}
