<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\CustomerRefundRequest;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class ManualPayment
{
    public function reconcileTransfer(User $actor, string $number, int $amount, ?string $reference, ?string $note): void
    {
        Gate::forUser($actor)->authorize('operate-orders');

        DB::transaction(function () use ($actor, $number, $amount, $reference, $note): void {
            [$order, $payment] = $this->lockOrderAndPayment($number);
            if (! in_array($order->status->value, OrderStatus::transferReconciliationValues(), true)) {
                throw $this->invalid($number, 'expected_status', 'Đơn không còn đủ điều kiện đối chiếu. Vui lòng tải lại trang.', 409);
            }
            $this->requirePaymentState($order, $payment, PaymentMethod::BankTransfer, PaymentStatus::Pending);
            if (! $payment->matchesCollectedAmount($order->total_vnd, $amount)) {
                throw $this->invalid($number, 'amount_vnd', 'Số tiền đã thu phải khớp với tổng tiền của đơn.');
            }

            if ($amount > 0 && ($reference === null || trim($reference) === '')) {
                throw $this->invalid($number, 'collection_reference', 'Nhập mã giao dịch trên sao kê.');
            }
            if ($amount === 0 && $reference !== null) {
                throw $this->invalid($number, 'collection_reference', 'Đơn 0 ₫ không có mã giao dịch để đối chiếu.');
            }

            $payment->status = PaymentStatus::Paid;
            $payment->collection_reference = $reference;
            $payment->paid_at = now();
            try {
                $payment->save();
            } catch (UniqueConstraintViolationException) {
                throw $this->invalid($number, 'collection_reference', 'Mã giao dịch này đã được dùng để xác nhận một khoản thu.');
            }

            $this->recordHistory(
                $payment,
                $actor,
                PaymentStatus::Pending,
                $amount === 0 ? ($note ?? 'Đơn có tổng tiền 0 ₫, không phát sinh khoản thu.') : $note,
            );
        }, 5);
    }

    public function recordRefund(User $actor, string $number, PaymentMethod $method, string $reference, ?string $note): void
    {
        Gate::forUser($actor)->authorize('access-admin');

        DB::transaction(function () use ($actor, $number, $method, $reference, $note): void {
            [$order, $payment] = $this->lockOrderAndPayment($number);
            $this->requirePaymentState($order, $payment, $method, PaymentStatus::RefundPending);
            if (! in_array($order->status, [OrderStatus::Cancelled, OrderStatus::Rejected], true)) {
                throw $this->invalid($number, 'expected_status', 'Đơn không còn đủ điều kiện ghi nhận hoàn tiền. Vui lòng tải lại trang.', 409);
            }
            if (trim($reference) === '') {
                throw $this->invalid($number, 'refund_reference', 'Nhập tham chiếu giao dịch hoàn tiền.');
            }

            $payment->status = PaymentStatus::Refunded;
            $payment->refund_reference = $reference;
            $payment->refunded_at = now();
            $payment->save();
            CustomerRefundRequest::query()->where('order_id', $order->id)->where('status', 'pending')
                ->update(['status' => 'completed', 'completed_at' => now(), 'completed_by' => $actor->id, 'refund_reference' => $reference]);
            $this->recordHistory($payment, $actor, PaymentStatus::RefundPending, $note);
        }, 5);
    }

    /** @return array{Order, Payment} */
    private function lockOrderAndPayment(string $number): array
    {
        $order = Order::query()->where('order_number', $number)->lockForUpdate()->firstOrFail();

        return [$order, $order->payment()->lockForUpdate()->firstOrFail()];
    }

    private function requirePaymentState(Order $order, Payment $payment, PaymentMethod $method, PaymentStatus $expected): void
    {
        if ($payment->method !== $method || $payment->status !== $expected) {
            throw $this->invalid($order->order_number, 'expected_status', 'Thông tin thanh toán đã thay đổi. Vui lòng tải lại trang.', 409);
        }
    }

    private function recordHistory(Payment $payment, User $actor, PaymentStatus $previous, ?string $note): void
    {
        $payment->statusHistories()->create([
            'actor_id' => $actor->id,
            'from_status' => $previous->value,
            'to_status' => $payment->status->value,
            'reason' => $note,
        ]);
    }

    private function invalid(string $number, string $field, string $message, int $status = 422): ValidationException
    {
        return ValidationException::withMessages([$field => $message])
            ->status($status)->redirectTo(route('operations.payments.show', $number));
    }
}
