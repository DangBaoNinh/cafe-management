<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Models\Order;
use App\Models\ScheduledPickup;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class ScheduledPickups
{
    public function __construct(
        private PickupFees $fees,
        private OrderHandoff $handoff,
        private OrderCancellation $cancellation,
    ) {}

    public function reschedule(User $customer, string $number, string $input, int $revision, string $channel): void
    {
        DB::transaction(function () use ($customer, $number, $input, $revision, $channel): void {
            $customer = User::query()->lockForUpdate()->findOrFail($customer->id);
            Gate::forUser($customer)->authorize('access-customer-orders');
            $order = $customer->orders()->where('order_number', $number)->lockForUpdate()->firstOrFail();
            $pickup = $order->scheduledPickup()->lockForUpdate()->firstOrFail();
            BookingRules::authorizeBooking($customer);
            $this->require($pickup->isOpen($order) && $pickup->arrived_at === null && now()->lt($pickup->scheduled_at),
                'Chỉ đổi lịch trước giờ hẹn và trước khi ghi nhận đến.');
            $slot = BookingRules::slot($input);
            $this->require($pickup->revision === $revision, 'Lịch đã thay đổi. Vui lòng tải lại trang.');
            $this->require(! $slot->eq($pickup->scheduled_at), 'Chọn giờ mới khác giờ đang hẹn.');
            $this->require(abs($slot->getTimestamp() - $pickup->scheduled_at->getTimestamp()) <= 3600,
                'Giờ mới chênh quá 60 phút. Vui lòng đặt lịch mới.');

            $oldSlot = $pickup->scheduled_at;
            $pickup->revision++;
            $pickup->scheduled_at = $slot;
            $pickup->arrival_deadline = $slot->addMinutes(30);
            $pickup->save();
            $this->fees->add($pickup, 'reschedule', BookingRules::CHANGE_FEE, $channel, ['old_slot' => $oldSlot, 'new_slot' => $slot]);
        }, 5);
    }

    public function arrive(User $actor, string $code, string $channel): void
    {
        Gate::forUser($actor)->authorize('operate-orders');
        $orderId = ScheduledPickup::query()->where('code', $code)->firstOrFail()->order_id;
        $customerId = Order::query()->findOrFail($orderId)->user_id;

        DB::transaction(function () use ($actor, $orderId, $customerId, $channel): void {
            $customer = User::query()->lockForUpdate()->findOrFail($customerId);
            $order = Order::query()->lockForUpdate()->findOrFail($orderId);
            $pickup = $order->scheduledPickup()->lockForUpdate()->firstOrFail();
            $this->require($pickup->isOpen($order), 'Phiếu đã dùng, đã hết hạn hoặc đơn đã kết thúc.');
            if ($pickup->arrived_at !== null) {
                return;
            }

            $arrival = now()->toImmutable();
            $this->require($arrival->lt($pickup->arrival_deadline), 'Đã quá hạn nhận đơn.');
            $late = max(0, intdiv($arrival->getTimestamp() - $pickup->scheduled_at->getTimestamp(), 60));
            if ($late > 5) {
                $this->fees->add($pickup, 'late', ($late - 5) * 2000, $channel, ['late_minutes' => $late]);
            }
            $pickup->arrived_at = $arrival;
            $pickup->arrival_actor_id = $actor->id;
            $pickup->save();
            BookingRules::recordAttendance($customer, true, $arrival);
        }, 5);
    }

    public function handoff(User $actor, string $code, ?int $collected): void
    {
        Gate::forUser($actor)->authorize('operate-orders');
        $orderId = ScheduledPickup::query()->where('code', $code)->firstOrFail()->order_id;

        DB::transaction(function () use ($actor, $orderId, $collected): void {
            $order = Order::query()->lockForUpdate()->findOrFail($orderId);
            $pickup = $order->scheduledPickup()->lockForUpdate()->firstOrFail();
            $this->require($pickup->isOpen($order), 'Phiếu đã dùng, đã hết hạn hoặc đơn đã kết thúc.');
            $this->require($pickup->arrived_at !== null, 'Ghi nhận khách đến trước khi bàn giao.');
            $this->require($order->status === OrderStatus::Ready, 'Đơn chưa sẵn sàng để bàn giao.');
            $payment = $order->payment()->lockForUpdate()->firstOrFail();
            $order->setRelation('payment', $payment);
            $this->require($payment->allowsHandoff(), 'Thanh toán đơn chưa đủ điều kiện bàn giao.');
            $invoice = $this->fees->invoice($order, $pickup);
            $this->require(! $invoice['waiting'], 'Đối chiếu đủ phụ phí qua ứng dụng hoặc chuyển sang quầy sau khi giao dịch kết thúc.');
            $this->require(($collected ?? 0) === $invoice['total'], 'Số tiền đã thu phải khớp hóa đơn tại quầy.');

            $this->handoff->advanceLocked($order, $payment, OrderStatus::Completed,
                $payment->method === PaymentMethod::Cod ? $invoice['items'] : null, $actor);
            $pickup->fees()->where('channel', 'counter')->where('status', 'pending')->update([
                'status' => 'paid', 'paid_at' => $order->completed_at, 'counter_actor_id' => $actor->id,
            ]);
            $pickup->used_at = $order->completed_at;
            $pickup->save();
        }, 5);
    }

    public function expireDue(): int
    {
        $expired = 0;
        $now = now()->toImmutable();
        $due = ScheduledPickup::query()->whereNull('arrived_at')->whereNull('no_show_at')
            ->where('arrival_deadline', '<=', $now)
            ->whereHas('order', fn ($orders) => $orders->whereNotIn('status', OrderStatus::finishedValues()))
            ->with('order:id,user_id')->orderBy('arrival_deadline')->orderBy('id');
        $last = null;
        do {
            $batch = (clone $due)->when($last !== null, function ($query) use ($last): void {
                $query->where(fn ($next) => $next->where('arrival_deadline', '>', $last->arrival_deadline)
                    ->orWhere(fn ($sameTime) => $sameTime->where('arrival_deadline', $last->arrival_deadline)
                        ->where('id', '>', $last->id)));
            })->limit(100)->get();
            foreach ($batch as $candidate) {
                $expired += (int) $this->expire($candidate, $now);
            }
            $last = $batch->last();
        } while ($batch->count() === 100);

        return $expired;
    }

    private function expire(ScheduledPickup $candidate, CarbonImmutable $now): bool
    {
        // Locks follow customer -> order -> pickup, matching checkout and arrival.
        return DB::transaction(function () use ($candidate, $now): bool {
            $customer = User::query()->lockForUpdate()->findOrFail($candidate->order->user_id);
            $order = Order::query()->lockForUpdate()->findOrFail($candidate->order_id);
            $pickup = $order->scheduledPickup()->lockForUpdate()->firstOrFail();
            if (! $order->status->allowsAdminCancellation() || $pickup->arrived_at !== null
                || $pickup->no_show_at !== null || $pickup->arrival_deadline->gt($now)) {
                return false;
            }
            if (! $this->cancellation->record($order, null, 'Khách không đến nhận đơn trong 30 phút sau giờ hẹn.')) {
                return false;
            }
            $pickup->no_show_at = $pickup->arrival_deadline;
            $pickup->save();
            BookingRules::recordAttendance($customer, false, $pickup->no_show_at);

            return true;
        }, 5);
    }

    private function require(bool $allowed, string $message): void
    {
        if (! $allowed) {
            throw ValidationException::withMessages(['pickup_time' => $message]);
        }
    }
}
