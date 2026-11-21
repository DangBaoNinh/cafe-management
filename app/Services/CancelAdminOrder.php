<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class CancelAdminOrder
{
    public function __construct(private OrderCancellation $cancellation) {}

    public function cancel(User $admin, string $number, OrderStatus $expected, string $reason): void
    {
        Gate::forUser($admin)->authorize('access-admin');
        if (trim($reason) === '') {
            throw ValidationException::withMessages(['reason' => 'Nhập lý do hủy đơn.'])
                ->redirectTo(route('admin.orders.show', $number));
        }

        DB::transaction(function () use ($admin, $number, $expected, $reason): void {
            $order = Order::query()->where('order_number', $number)->lockForUpdate()->firstOrFail();
            if ($order->status !== $expected) {
                throw $this->conflict($number, 'Đơn đã đổi trạng thái. Vui lòng tải lại trang để xem trạng thái hiện tại.');
            }
            if (! $order->status->allowsAdminCancellation()) {
                throw $this->conflict($number, 'Không thể hủy đơn ở trạng thái hiện tại.');
            }
            if (! $this->cancellation->record($order, $admin, $reason)) {
                throw $this->conflict($number, 'Không thể hủy đơn với trạng thái thanh toán hiện tại.');
            }
        }, 5);
    }

    private function conflict(string $number, string $message): ValidationException
    {
        return ValidationException::withMessages(['expected_status' => $message])
            ->status(409)->redirectTo(route('admin.orders.show', $number));
    }
}
