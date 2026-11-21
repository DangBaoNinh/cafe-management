<?php

namespace App\Services;

use App\Enums\DemoShipmentEvent;
use App\Enums\FulfillmentType;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Models\DemoShipment;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class DemoDeliveryProvider
{
    public function __construct(private OrderHandoff $handoff) {}

    public static function enabled(): bool
    {
        return app()->environment('local', 'testing');
    }

    public function createShipment(User $actor, string $number, bool $simulateUnavailable): DemoShipment
    {
        Gate::forUser($actor)->authorize('operate-orders');

        return DB::transaction(function () use ($actor, $number, $simulateUnavailable): DemoShipment {
            $order = Order::query()->where('order_number', $number)->lockForUpdate()->firstOrFail();
            $shipment = $order->demoShipment()->first();
            if ($shipment !== null) {
                return $shipment;
            }
            if ($order->fulfillment_type !== FulfillmentType::Delivery || $order->status !== OrderStatus::Ready) {
                throw $this->invalid($number, 'Chỉ tạo vận đơn cho đơn giao đang sẵn sàng.', 409);
            }
            if (! $order->payment()->lockForUpdate()->first()?->allowsHandoff()) {
                throw $this->invalid($number, 'Thanh toán chưa đủ điều kiện để tạo vận đơn.', 409);
            }
            if (blank($order->address_snapshot['address_line'] ?? null)) {
                throw $this->invalid($number, 'Đơn chưa có địa chỉ giao hàng.');
            }
            if ($simulateUnavailable) {
                throw $this->invalid($number, 'Đối tác mô phỏng chưa phản hồi. Có thể thử lại hoặc bàn giao thủ công.', 503);
            }

            return $order->demoShipment()->create([
                'tracking_number' => Str::uuid()->toString(),
                'created_by' => $actor->id,
            ]);
        }, 5);
    }

    public function simulate(User $actor, string $number, DemoShipmentEvent $event, ?int $codAmount): void
    {
        Gate::forUser($actor)->authorize('operate-orders');
        $shipment = Order::query()->where('order_number', $number)->firstOrFail()->demoShipment()->firstOrFail();
        $body = json_encode([
            'tracking_number' => $shipment->tracking_number,
            'event_id' => $shipment->tracking_number.':'.$event->value,
            'event' => $event->value,
            'cod_amount_vnd' => $codAmount,
        ], JSON_THROW_ON_ERROR);

        $this->receive($body, $this->sign($body));
    }

    public function receive(string $body, ?string $signature): void
    {
        abort_unless($signature !== null && hash_equals($this->sign($body), $signature), 401, 'Chữ ký sự kiện vận chuyển không hợp lệ.');
        $payload = json_decode($body, true);
        $data = Validator::make(is_array($payload) ? $payload : [], [
            'tracking_number' => ['required', 'uuid'],
            'event_id' => ['required', 'string', 'max:64'],
            'event' => ['required', Rule::enum(DemoShipmentEvent::class)],
            'cod_amount_vnd' => ['nullable', 'integer:strict', 'min:0'],
        ])->validate();
        $event = DemoShipmentEvent::from($data['event']);
        $amount = $data['cod_amount_vnd'] ?? null;
        $tracking = $data['tracking_number'];
        abort_unless($data['event_id'] === $tracking.':'.$event->value, 422, 'Mã sự kiện không khớp vận đơn.');
        $reported = DemoShipment::query()->where('tracking_number', $tracking)->firstOrFail();
        abort_unless($reported->tracking_number === $tracking, 422, 'Mã vận đơn không khớp.');

        DB::transaction(function () use ($reported, $event, $amount): void {
            $order = Order::query()->lockForUpdate()->findOrFail($reported->order_id);
            $payment = $order->payment()->lockForUpdate()->firstOrFail();
            $shipment = $order->demoShipment()->lockForUpdate()->findOrFail($reported->id);
            $delivered = $event === DemoShipmentEvent::Delivered;
            $milestone = $delivered ? 'delivered_at' : 'picked_up_at';

            if ($shipment->{$milestone} !== null) {
                $sameAmount = $delivered && $payment->method === PaymentMethod::Cod
                    ? $amount !== null && $payment->matchesCollectedAmount($order->total_vnd, $amount)
                    : $amount === null;
                if (! $sameAmount) {
                    throw $this->invalid($order->order_number, 'Số tiền COD không khớp sự kiện đã ghi.');
                }

                return;
            }

            if ($order->fulfillment_type !== FulfillmentType::Delivery || ($delivered && $shipment->picked_up_at === null)) {
                throw $this->invalid($order->order_number, 'Sự kiện vận chuyển đến sai thứ tự.', 409);
            }
            $this->handoff->advanceLocked(
                $order, $payment, $delivered ? OrderStatus::Completed : OrderStatus::OutForDelivery,
                $amount, null, 'Cập nhật từ đối tác vận chuyển mô phỏng.',
            );
            $shipment->{$milestone} = $delivered ? $order->completed_at : now();
            $shipment->save();
        }, 5);
    }

    private function invalid(string $number, string $message, int $status = 422): ValidationException
    {
        return ValidationException::withMessages(['shipment' => $message])
            ->status($status)->redirectTo(route('operations.orders.show', $number));
    }

    private function sign(string $body): string
    {
        $appKey = Config::string('app.key', '');
        abort_if($appKey === '', 503, 'Đối tác mô phỏng chưa có khóa ký.');
        $key = hash_hmac('sha256', 'coffee-shop:demo-delivery:v1', $appKey, true);

        return hash_hmac('sha256', $body, $key);
    }
}
