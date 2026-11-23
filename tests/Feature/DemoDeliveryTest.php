<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class DemoDeliveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_unavailable_provider_can_be_retried_with_one_shipment_per_order(): void
    {
        $staff = User::factory()->create(['role' => UserRole::Staff]);
        $order = $this->order(User::factory()->create());
        $create = route('operations.orders.demo-shipment.create', $order->order_number);
        $show = route('operations.orders.show', $order->order_number);

        $this->actingAs($staff)->get($show)->assertOk()->assertSee('Tạo vận đơn mô phỏng')->assertSee('Đánh dấu đang giao');
        $this->postJson($create, ['simulate_unavailable' => true])->assertStatus(503)->assertJsonValidationErrors('shipment');
        $this->assertDatabaseCount('demo_shipments', 0);
        $this->assertSame('ready', $order->refresh()->status->value);
        $this->post($create)->assertStatus(303)->assertRedirect($show);
        $tracking = $order->demoShipment()->sole()->tracking_number;
        $this->assertSame($staff->id, $order->demoShipment()->sole()->created_by);
        $this->post($create, ['simulate_unavailable' => '1'])->assertStatus(303);
        $this->assertDatabaseCount('demo_shipments', 1);
        $this->assertSame($tracking, $order->demoShipment()->sole()->tracking_number);
        $this->get($show)->assertOk()->assertSee($tracking)->assertSee('Mô phỏng đối tác nhận đơn')->assertDontSee('Đánh dấu đang giao');
        $this->postJson(route('operations.orders.handoff', $order->order_number), $this->handoffInput('delivery', 'ready', 'out_for_delivery'))
            ->assertConflict();
    }

    public function test_callback_authenticates_ordered_updates_and_collects_exact_cod_once(): void
    {
        $customer = User::factory()->create();
        $staff = User::factory()->create(['role' => UserRole::Staff]);
        $order = $this->order($customer);
        $this->actingAs($staff)->post(route('operations.orders.demo-shipment.create', $order->order_number))->assertStatus(303);
        $shipment = $order->demoShipment()->sole();
        $tracking = $shipment->tracking_number;
        $payment = $order->payment()->sole();

        $this->postEvent($tracking, 'picked_up', validSignature: false)->assertUnauthorized();
        $this->postEvent(Str::uuid()->toString(), 'picked_up')->assertNotFound();
        $this->postEvent($tracking, 'picked_up', eventId: 'wrong')->assertUnprocessable();
        $this->postEvent($tracking, 'delivered', 52000)->assertConflict();
        $this->assertSame('ready', $order->refresh()->status->value);
        $this->assertDatabaseCount('order_status_histories', 0);
        $this->postEvent($tracking, 'picked_up')->assertNoContent();
        $this->assertSame('out_for_delivery', $order->refresh()->status->value);
        $this->assertSame('pending', $payment->refresh()->status->value);
        $this->assertNotNull($shipment->refresh()->picked_up_at);
        $this->postEvent($tracking, 'picked_up')->assertNoContent();
        $this->postEvent($tracking, 'delivered')->assertUnprocessable()->assertJsonValidationErrors('cod_amount_vnd');
        $this->postEvent($tracking, 'delivered', 40000)->assertUnprocessable()->assertJsonValidationErrors('cod_amount_vnd');
        $this->assertNull($shipment->refresh()->delivered_at);
        $this->assertNull($order->refresh()->completed_at);
        $this->assertSame('pending', $payment->refresh()->status->value);
        $this->postEvent($tracking, 'delivered', 52000)->assertNoContent();
        $this->postEvent($tracking, 'delivered', 52000)->assertNoContent();
        $this->postEvent($tracking, 'delivered', 40000)->assertUnprocessable();
        $this->assertSame('completed', $order->refresh()->status->value);
        $this->assertSame('paid', $payment->refresh()->status->value);
        $this->assertEquals($order->completed_at, $payment->paid_at);
        $this->assertEquals($order->completed_at, $shipment->refresh()->delivered_at);
        $this->assertSame(['out_for_delivery', 'completed'], $order->statusHistories()->orderBy('id')->pluck('to_status')->all());
        $this->assertSame('paid', $payment->statusHistories()->sole()->to_status);
        $this->actingAs($customer)->get(route('orders.show', $order->order_number))
            ->assertOk()->assertSee('Đã hoàn tất')->assertDontSee($tracking)->assertDontSee('Cập nhật từ đối tác vận chuyển mô phỏng.');
    }

    public function test_pickup_and_manual_fallback_keep_the_existing_handoff_flow(): void
    {
        $staff = User::factory()->create(['role' => UserRole::Staff]);
        $owner = User::factory()->create();
        $pickup = $this->order($owner, 'pickup');
        $delivery = $this->order($owner);
        $this->actingAs($staff)->get(route('operations.orders.show', $pickup->order_number))
            ->assertOk()->assertDontSee('Tạo vận đơn mô phỏng')->assertSee('Hoàn tất bàn giao');
        $this->postJson(route('operations.orders.demo-shipment.create', $pickup->order_number))->assertConflict();
        $this->post(route('operations.orders.handoff', $pickup->order_number), $this->handoffInput('pickup', 'ready', 'completed', 40000))
            ->assertStatus(303);
        $this->postJson(route('operations.orders.demo-shipment.create', $delivery->order_number), ['simulate_unavailable' => true])
            ->assertStatus(503);
        $this->post(route('operations.orders.handoff', $delivery->order_number), $this->handoffInput('delivery', 'ready', 'out_for_delivery'))
            ->assertStatus(303);
        $this->assertSame('completed', $pickup->refresh()->status->value);
        $this->assertSame('out_for_delivery', $delivery->refresh()->status->value);
        $this->assertDatabaseCount('demo_shipments', 0);
    }

    public function test_prepaid_orders_require_payment_before_booking_and_collect_no_cod(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $order = $this->order(User::factory()->create(), method: 'bank_transfer');
        $number = $order->order_number;
        $create = route('operations.orders.demo-shipment.create', $number);
        $event = route('operations.orders.demo-shipment.simulate', $number);
        $this->actingAs($admin)->postJson($create)->assertConflict();
        $order->payment()->update(['status' => 'paid']);
        $this->post($create)->assertStatus(303);
        $this->post($event, ['event' => 'picked_up'])->assertStatus(303);
        $this->postJson($event, ['event' => 'delivered', 'cod_amount_vnd' => 52000])
            ->assertUnprocessable()->assertJsonValidationErrors('cod_amount_vnd');
        $this->post($event, ['event' => 'delivered'])->assertStatus(303);
        $this->assertSame('completed', $order->refresh()->status->value);
        $this->assertDatabaseCount('payment_status_histories', 0);
    }

    public function test_unauthorized_users_and_terminal_orders_cannot_change_the_shipment(): void
    {
        $owner = User::factory()->create();
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $order = $this->order($owner);
        $number = $order->order_number;
        $create = route('operations.orders.demo-shipment.create', $number);
        $event = route('operations.orders.demo-shipment.simulate', $number);
        $this->post($create)->assertRedirectToRoute('login');
        $this->actingAs($owner)->post($create)->assertForbidden();
        $this->post($event, ['event' => 'picked_up'])->assertForbidden();
        $this->actingAs($admin)->post($create)->assertStatus(303);
        $tracking = $order->demoShipment()->sole()->tracking_number;
        $this->post(route('admin.orders.cancel', $number), ['expected_status' => 'ready', 'reason' => 'Hủy thử'])
            ->assertStatus(303);
        $this->postEvent($tracking, 'picked_up')->assertConflict();
        $this->assertSame('cancelled', $order->refresh()->status->value);
        $this->assertNull($order->demoShipment()->sole()->picked_up_at);
        $this->app->instance('env', 'production');
        $this->post($create, [], ['Sec-Fetch-Site' => 'same-origin'])->assertNotFound();
        $this->postEvent($tracking, 'picked_up')->assertNotFound();
    }

    public function test_failed_shipment_update_rolls_back_the_order_payment_and_histories(): void
    {
        $staff = User::factory()->create(['role' => UserRole::Staff]);
        $order = $this->order(User::factory()->create());
        $this->actingAs($staff)->post(route('operations.orders.demo-shipment.create', $order->order_number))->assertStatus(303);
        $shipment = $order->demoShipment()->sole();
        $this->postEvent($shipment->tracking_number, 'picked_up')->assertNoContent();

        DB::unprepared("CREATE TEMP TRIGGER reject_delivery BEFORE UPDATE OF delivered_at ON demo_shipments
            WHEN NEW.delivered_at IS NOT NULL BEGIN SELECT RAISE(ABORT, 'test shipment update failure'); END");
        try {
            $this->postEvent($shipment->tracking_number, 'delivered', 52000)->assertServerError();
        } finally {
            DB::unprepared('DROP TRIGGER reject_delivery');
        }

        $this->assertSame('out_for_delivery', $order->refresh()->status->value);
        $this->assertNull($order->completed_at);
        $this->assertSame('pending', $order->payment()->sole()->status->value);
        $this->assertDatabaseCount('payment_status_histories', 0);
        $this->assertDatabaseCount('order_status_histories', 1);
        $this->assertNull($shipment->refresh()->delivered_at);
        $this->postEvent($shipment->tracking_number, 'delivered', 52000)->assertNoContent();
    }

    private function order(User $owner, string $fulfillment = 'delivery', string $method = 'cod'): Order
    {
        $fee = $fulfillment === 'delivery' ? 12000 : 0;
        $order = $owner->orders()->create([
            'order_number' => 'CF-'.Str::ulid(), 'checkout_key' => Str::uuid()->toString(),
            'request_hash' => hash('sha256', 'demo'), 'recipient_name' => 'Khách mô phỏng', 'phone' => '0901234567',
            'fulfillment_type' => $fulfillment, 'status' => 'ready', 'subtotal_vnd' => 40000,
            'discount_vnd' => 0, 'delivery_fee_vnd' => $fee, 'total_vnd' => 40000 + $fee,
            'address_snapshot' => $fee === 0 ? null : ['address_line' => '10 Nguyễn Huệ'],
        ]);
        $order->payment()->create(['method' => $method, 'status' => 'pending', 'amount_vnd' => $order->total_vnd]);

        return $order;
    }

    /** @return array<string, int|string> */
    private function handoffInput(string $type, string $from, string $to, ?int $amount = null): array
    {
        return array_filter([
            'fulfillment_type' => $type, 'method' => 'cod', 'expected_status' => $from,
            'target_status' => $to, 'confirmed' => '1', 'cod_amount_vnd' => $amount,
            'collection_method' => $to === 'completed' ? 'cash' : null,
        ], fn ($value) => $value !== null);
    }

    private function postEvent(string $tracking, string $event, ?int $amount = null, bool $validSignature = true, ?string $eventId = null): TestResponse
    {
        $body = json_encode([
            'tracking_number' => $tracking, 'event_id' => $eventId ?? "$tracking:$event", 'event' => $event, 'cod_amount_vnd' => $amount,
        ], JSON_THROW_ON_ERROR);
        $key = hash_hmac('sha256', 'coffee-shop:demo-delivery:v1', config('app.key'), true);

        return $this->call('POST', route('deliveries.demo.callback'), [], [], [], [
            'HTTP_ACCEPT' => 'application/json', 'CONTENT_TYPE' => 'application/json',
            'HTTP_X_DEMO_SIGNATURE' => $validSignature ? hash_hmac('sha256', $body, $key) : 'wrong',
        ], $body);
    }
}
