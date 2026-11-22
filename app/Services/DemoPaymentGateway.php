<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\DemoPaymentSession;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class DemoPaymentGateway
{
    public static function enabled(): bool
    {
        return app()->environment('local', 'testing');
    }

    public static function canPay(Order $order, ?Payment $payment): bool
    {
        return $payment?->method === PaymentMethod::DemoGateway
            && $payment->status === PaymentStatus::Pending
            && in_array($order->status, [OrderStatus::Pending, OrderStatus::Confirmed], true);
    }

    public function start(User $customer, string $number): DemoPaymentSession
    {
        return DB::transaction(function () use ($customer, $number): DemoPaymentSession {
            $order = $customer->orders()->where('order_number', $number)->lockForUpdate()->firstOrFail();
            $payment = $order->payment()->lockForUpdate()->firstOrFail();
            abort_unless(self::canPay($order, $payment), 409, 'Đơn không còn chờ thanh toán mô phỏng.');
            $previous = $payment->demoSessions()->latest('id')->first();
            if ($previous !== null && $previous->result !== DemoPaymentSession::FAILED) {
                return $previous;
            }

            return $payment->demoSessions()->create([
                'reference' => Str::uuid()->toString(),
                'amount_vnd' => $payment->amount_vnd,
            ]);
        }, 5);
    }

    public function simulate(User $customer, string $number, string $reference, string $result, bool $sendCallback): void
    {
        $session = DB::transaction(function () use ($customer, $number, $reference, $result): DemoPaymentSession {
            $order = $customer->orders()->where('order_number', $number)->lockForUpdate()->firstOrFail();
            $payment = $order->payment()->lockForUpdate()->firstOrFail();
            $session = $payment->demoSessions()->where('reference', $reference)->lockForUpdate()->firstOrFail();
            abort_unless(self::canPay($order, $payment) && $session->result === null, 409, 'Phiên thanh toán đã kết thúc.');
            $session->result = $result;
            $session->resolved_at = now();
            $session->save();

            return $session;
        }, 5);

        if ($sendCallback) {
            $this->deliver($session);
        }
    }

    public function reconcile(User $admin, string $number): bool
    {
        Gate::forUser($admin)->authorize('access-admin');
        $payment = Order::query()->where('order_number', $number)->firstOrFail()->payment()
            ->where('method', PaymentMethod::DemoGateway)->firstOrFail();
        $session = $payment->demoSessions()->where('result', DemoPaymentSession::SUCCEEDED)->first();
        if ($session === null) {
            return false;
        }

        $this->deliver($session, $admin);

        return true;
    }

    public function receive(string $body, ?string $signature, ?User $admin = null): void
    {
        abort_unless($signature !== null && hash_equals($this->sign($body), $signature), 401, 'Chữ ký callback không hợp lệ.');
        $event = json_decode($body, true);
        $reference = is_array($event) ? ($event['reference'] ?? null) : null;
        abort_unless(is_string($reference) && Str::isUuid($reference), 422, 'Tham chiếu phiên không hợp lệ.');
        $reported = DemoPaymentSession::query()->with('payment')->where('reference', $reference)->firstOrFail();

        DB::transaction(function () use ($reported, $body, $admin): void {
            $order = Order::query()->lockForUpdate()->findOrFail($reported->payment->order_id);
            $payment = $order->payment()->lockForUpdate()->firstOrFail();
            $session = $payment->demoSessions()->lockForUpdate()->findOrFail($reported->id);
            abort_unless(
                $payment->method === PaymentMethod::DemoGateway
                    && $session->result !== null
                    && hash_equals($this->eventBody($session), $body)
                    && $payment->matchesCollectedAmount($order->total_vnd, $session->amount_vnd),
                422,
                'Callback không khớp giao dịch của cổng mô phỏng.',
            );

            if ($session->applied_at !== null) {
                return;
            }
            if ($session->result === DemoPaymentSession::SUCCEEDED) {
                $this->recordPayment($order, $payment, $session, $admin);
            }
            $session->applied_at = now();
            $session->save();
        }, 5);
    }

    private function recordPayment(Order $order, Payment $payment, DemoPaymentSession $session, ?User $admin): void
    {
        $previous = $payment->status;
        if (self::canPay($order, $payment)) {
            $payment->status = PaymentStatus::Paid;
        } elseif ($previous === PaymentStatus::Voided && in_array($order->status, [OrderStatus::Cancelled, OrderStatus::Rejected], true)) {
            if ($session->amount_vnd === 0) {
                return;
            }
            $payment->status = PaymentStatus::RefundPending;
        } else {
            abort(409, 'Trạng thái đơn không cho phép ghi nhận giao dịch này.');
        }

        $payment->paid_at = $session->resolved_at;
        $payment->collection_reference = $session->reference;
        $payment->save();
        $payment->statusHistories()->create([
            'actor_id' => $admin?->id,
            'from_status' => $previous->value,
            'to_status' => $payment->status->value,
            'reason' => $admin === null ? 'Callback cổng thanh toán mô phỏng.' : 'Đối chiếu cổng thanh toán mô phỏng.',
        ]);
        CustomerRefunds::refundLateDemoReceipt($order);
    }

    private function deliver(DemoPaymentSession $session, ?User $admin = null): void
    {
        $body = $this->eventBody($session);
        $this->receive($body, $this->sign($body), $admin);
    }

    private function eventBody(DemoPaymentSession $session): string
    {
        return json_encode([
            'provider' => 'demo',
            'event_id' => 'demo:'.$session->reference,
            'reference' => $session->reference,
            'result' => $session->result,
            'amount_vnd' => $session->amount_vnd,
            'currency' => 'VND',
        ], JSON_THROW_ON_ERROR);
    }

    private function sign(string $body): string
    {
        $appKey = Config::string('app.key', '');
        abort_if($appKey === '', 503, 'Cổng mô phỏng chưa có khóa ký.');
        $key = hash_hmac('sha256', 'coffee-shop:demo-payment:v1', $appKey, true);

        return hash_hmac('sha256', $body, $key);
    }
}
