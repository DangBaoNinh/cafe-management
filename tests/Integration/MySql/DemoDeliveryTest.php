<?php

namespace Tests\Integration\MySql;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DemoDeliveryTest extends MySqlTestCase
{
    public function test_retry_reuses_the_shipment_and_delivered_cod_is_recorded_once(): void
    {
        DB::setDefaultConnection($this->primary->getName());
        $this->primary->beginTransaction();

        try {
            $owner = User::factory()->create();
            $staff = User::factory()->create(['role' => UserRole::Staff]);
            $number = 'CF-'.Str::ulid();
            $order = $owner->orders()->create([
                'order_number' => $number, 'checkout_key' => Str::uuid()->toString(),
                'request_hash' => hash('sha256', $number), 'recipient_name' => 'Khách thử', 'phone' => '0901234567',
                'fulfillment_type' => 'delivery', 'status' => 'ready', 'subtotal_vnd' => 40000,
                'discount_vnd' => 0, 'delivery_fee_vnd' => 12000, 'total_vnd' => 52000,
                'address_snapshot' => ['address_line' => '10 Nguyễn Huệ'],
            ]);
            $payment = $order->payment()->create(['method' => 'cod', 'status' => 'pending', 'amount_vnd' => 52000]);
            $create = route('operations.orders.demo-shipment.create', $number);
            $event = route('operations.orders.demo-shipment.simulate', $number);

            $this->actingAs($staff)->post($create)->assertStatus(303);
            $shipment = $order->demoShipment()->sole();
            $this->post($create)->assertStatus(303);
            $this->assertSame($shipment->tracking_number, $order->demoShipment()->sole()->tracking_number);
            $this->post($event, ['event' => 'picked_up'])->assertStatus(303);
            $this->assertSame('pending', $payment->refresh()->status->value);
            $this->postJson($event, ['event' => 'delivered', 'cod_amount_vnd' => 52001])->assertUnprocessable();
            $this->assertNull($shipment->refresh()->delivered_at);
            $completed = ['event' => 'delivered', 'cod_amount_vnd' => 52000];
            $this->post($event, $completed)->assertStatus(303);
            $this->post($event, $completed)->assertStatus(303);
            $this->assertSame('completed', $order->refresh()->status->value);
            $this->assertSame('paid', $payment->refresh()->status->value);
            $this->assertEquals($order->completed_at, $payment->paid_at);
            $this->assertEquals($order->completed_at, $shipment->refresh()->delivered_at);
            $this->assertSame(1, $payment->statusHistories()->count());
            $this->assertSame(2, $order->statusHistories()->count());
        } finally {
            $this->primary->rollBack();
        }
    }
}
