<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class OrderPreparation
{
    public function advance(User $actor, string $number, OrderStatus $expected, OrderStatus $target): void
    {
        Gate::forUser($actor)->authorize('operate-orders');

        DB::transaction(function () use ($actor, $number, $expected, $target): void {
            $order = Order::query()->where('order_number', $number)->lockForUpdate()->firstOrFail();
            $required = match ($target) {
                OrderStatus::Preparing => OrderStatus::Confirmed,
                OrderStatus::Ready => OrderStatus::Preparing,
                default => null,
            };
            if ($order->status !== $expected || $order->status !== $required) {
                throw $this->conflict($number, 'expected_status', 'Đơn đã đổi trạng thái hoặc thao tác không đúng thứ tự. Vui lòng tải lại trang.');
            }

            if ($target === OrderStatus::Preparing && ! $order->payment()->lockForUpdate()->first()?->allowsPreparation()) {
                throw $this->conflict($number, 'payment', 'Thanh toán chưa đủ điều kiện để bắt đầu pha chế.');
            }

            $order->update(['status' => $target]);
            $order->statusHistories()->create([
                'actor_id' => $actor->id,
                'from_status' => $expected->value,
                'to_status' => $target->value,
            ]);
        }, 5);
    }

    private function conflict(string $number, string $field, string $message): ValidationException
    {
        return ValidationException::withMessages([$field => $message])
            ->status(409)->redirectTo(route('operations.orders.show', $number));
    }
}
