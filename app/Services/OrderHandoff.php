<?php

namespace App\Services;

use App\Enums\FulfillmentType;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class OrderHandoff
{
    public function advance(
        User $actor,
        string $number,
        OrderStatus $expected,
        OrderStatus $target,
        FulfillmentType $fulfillment,
        PaymentMethod $method,
        ?int $collectedAmount,
    ): void {
        Gate::forUser($actor)->authorize('operate-orders');

        DB::transaction(function () use ($actor, $number, $expected, $target, $fulfillment, $method, $collectedAmount): void {
            $order = Order::query()->where('order_number', $number)->lockForUpdate()->firstOrFail();
            if ($order->fulfillment_type !== $fulfillment) {
                throw $this->invalid($number, 'fulfillment_type', 'Cách nhận đã thay đổi. Vui lòng tải lại trang.', 409);
            }
            if ($order->status !== $expected || $order->nextHandoffStatus() !== $target) {
                throw $this->invalid($number, 'expected_status', 'Đơn đã đổi trạng thái hoặc thao tác bàn giao không đúng thứ tự. Vui lòng tải lại trang.', 409);
            }

            $payment = $order->payment()->lockForUpdate()->first();
            if ($payment?->method !== $method) {
                throw $this->invalid($number, 'method', 'Thông tin thanh toán không khớp. Vui lòng tải lại trang.', 409);
            }
            if (! $payment->allowsHandoff()) {
                throw $this->invalid($number, 'payment', 'Thanh toán chưa đủ điều kiện để bàn giao đơn.', 409);
            }

            $completedAt = $target === OrderStatus::Completed ? now() : null;
            if ($completedAt !== null && $method === PaymentMethod::Cod) {
                if ($collectedAmount === null || ! $payment->matchesCollectedAmount($order->total_vnd, $collectedAmount)) {
                    throw $this->invalid($number, 'cod_amount_vnd', 'Số tiền COD đã thu phải khớp với tổng tiền của đơn.');
                }
                $payment->status = PaymentStatus::Paid;
                $payment->paid_at = $completedAt;
                $payment->save();
                $payment->statusHistories()->create([
                    'actor_id' => $actor->id,
                    'from_status' => PaymentStatus::Pending->value,
                    'to_status' => PaymentStatus::Paid->value,
                ]);
            } elseif ($collectedAmount !== null) {
                throw $this->invalid($number, 'cod_amount_vnd', 'Chỉ nhập số tiền COD khi hoàn tất đơn thanh toán khi nhận.');
            }

            $order->status = $target;
            if ($completedAt !== null) {
                $order->completed_at = $completedAt;
            }
            $order->save();
            $order->statusHistories()->create([
                'actor_id' => $actor->id,
                'from_status' => $expected->value,
                'to_status' => $target->value,
            ]);
        }, 5);
    }

    private function invalid(string $number, string $field, string $message, int $status = 422): ValidationException
    {
        return ValidationException::withMessages([$field => $message])
            ->status($status)->redirectTo(route('operations.orders.show', $number));
    }
}
