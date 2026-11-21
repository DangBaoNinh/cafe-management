<?php

namespace Tests\Feature;

use App\Enums\PaymentStatus;
use App\Enums\UserRole;
use App\Models\DemoPaymentSession;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class DemoPaymentTest extends TestCase
{
    use RefreshDatabase;

    public function test_abandon_failure_retry_and_success_use_the_same_pending_payment(): void
    {
        $customer = User::factory()->create();
        $order = $this->order($customer);
        $start = route('orders.demo-payment.start', $order->order_number);
        $detail = route('orders.show', $order->order_number);
        $payment = $order->payment()->sole();
        $this->actingAs($customer)->post($start)->assertStatus(303);
        $session = $payment->demoSessions()->sole();
        $gateway = route('orders.demo-payment.show', [$order->order_number, $session->reference]);
        $this->get($gateway)->assertOk()->assertSee('Thanh toán thành công')->assertSee('42.000');
        $this->get($detail)->assertOk()->assertSee('Chờ thanh toán');
        $this->post($start)->assertRedirect($gateway);
        $this->assertDatabaseCount('demo_payment_sessions', 1);

        $this->post($gateway, ['scenario' => 'failure'])->assertRedirect($detail);
        $this->assertSame(PaymentStatus::Pending, $payment->refresh()->status);
        $this->assertSame('failed', $session->refresh()->result);
        $this->assertNotNull($session->applied_at);
        $this->assertDatabaseCount('payment_status_histories', 0);
        $this->post($start)->assertStatus(303);
        $retry = $payment->demoSessions()->latest('id')->firstOrFail();
        $this->assertNotSame($session->reference, $retry->reference);
        $this->post(route('orders.demo-payment.simulate', [$order->order_number, $retry->reference]), ['scenario' => 'success'])
            ->assertRedirect($detail);
        $this->get($detail)->assertOk()->assertSee('Đã thanh toán')->assertDontSee('Mở cổng thanh toán mô phỏng');
        $this->assertSame(PaymentStatus::Paid, $payment->refresh()->status);
        $this->assertTrue($payment->allowsPreparation());
        $this->assertTrue($payment->allowsHandoff());
        $this->assertDatabaseCount('payments', 1);
        $this->assertDatabaseHas('payment_status_histories', ['payment_id' => $payment->id, 'from_status' => 'pending', 'to_status' => 'paid']);
    }

    public function test_callback_checks_signature_and_saved_amount_and_applies_a_valid_event_once(): void
    {
        $customer = User::factory()->create();
        $order = $this->order($customer);
        $session = $this->simulateWithoutCallback($customer, $order);
        $payment = $order->payment()->sole();
        $this->assertSame(PaymentStatus::Pending, $payment->status);
        $body = $this->event($session);

        $this->postCallback($body, 'invalid')->assertUnauthorized();
        $this->postCallback($this->event($session, ['amount_vnd' => 42001]))->assertUnprocessable();
        $this->postCallback($this->event($session, ['provider' => 'other']))->assertUnprocessable();
        $this->postCallback($this->event($session, ['event_id' => 'new-event']))->assertUnprocessable();
        $this->postCallback($this->event($session, ['reference' => Str::uuid()->toString()]))->assertNotFound();
        $this->assertNull($session->refresh()->applied_at);
        $this->assertSame(PaymentStatus::Pending, $payment->refresh()->status);
        $this->postCallback($body)->assertNoContent();
        $recorded = $session->refresh()->applied_at;
        $this->travel(1)->minutes();
        $this->postCallback($body)->assertNoContent();
        $this->assertEquals($recorded, $session->refresh()->applied_at);
        $this->assertSame(PaymentStatus::Paid, $payment->refresh()->status);
        $this->assertEquals($session->resolved_at, $payment->paid_at);
        $this->assertSame($session->reference, $payment->collection_reference);
        $this->assertDatabaseCount('payment_status_histories', 1);
    }

    public function test_admin_reconciles_when_callback_is_missing_and_a_late_delivery_cannot_repeat_it(): void
    {
        $owner = User::factory()->create();
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $staff = User::factory()->create(['role' => UserRole::Staff]);
        $order = $this->order($owner);
        $session = $this->simulateWithoutCallback($owner, $order);
        $reconcile = route('operations.payments.demo-reconcile', $order->order_number);
        $paymentLink = route('operations.payments.show', $order->order_number);
        $this->actingAs($staff)->post($reconcile)->assertForbidden();
        $this->get(route('operations.payments.index'))->assertOk()->assertDontSee($paymentLink);
        $this->actingAs($admin)->get(route('operations.payments.index'))->assertOk()->assertSee($paymentLink);
        $this->get($paymentLink)->assertOk()->assertSee('Đối chiếu giao dịch mô phỏng');
        $this->post($reconcile)->assertStatus(303)->assertRedirect($paymentLink);
        $this->post($reconcile)->assertStatus(303)->assertRedirect($paymentLink);
        $this->postCallback($this->event($session))->assertNoContent();
        $payment = $order->payment()->sole();
        $this->assertSame(PaymentStatus::Paid, $payment->status);
        $this->assertSame($admin->id, $payment->statusHistories()->sole()->actor_id);
        $this->get(route('operations.payments.index'))->assertOk()->assertDontSee($paymentLink);
    }

    public function test_success_delivered_after_cancellation_requires_refund_and_does_not_reopen_order(): void
    {
        $owner = User::factory()->create();
        $order = $this->order($owner);
        $session = $this->simulateWithoutCallback($owner, $order);
        $this->post(route('orders.cancel', $order->order_number), ['expected_status' => 'pending', 'confirm' => '1'])
            ->assertStatus(303);
        $this->assertSame(PaymentStatus::Voided, $order->payment()->sole()->status);
        $this->postCallback($this->event($session))->assertNoContent();
        $this->assertSame('cancelled', $order->refresh()->status->value);
        $this->assertSame(PaymentStatus::RefundPending, $order->payment()->sole()->status);
        $this->postCallback($this->event($session))->assertNoContent();
        $this->assertDatabaseCount('payment_status_histories', 2);
    }

    public function test_only_the_owner_can_use_a_session_and_demo_routes_are_disabled_in_production(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $order = $this->order($owner);
        $otherOrder = $this->order($other);
        $start = route('orders.demo-payment.start', $order->order_number);
        $this->post($start)->assertRedirectToRoute('login');
        $this->actingAs($owner)->post($start)->assertStatus(303);
        $session = $order->payment()->sole()->demoSessions()->sole();
        $url = route('orders.demo-payment.show', [$order->order_number, $session->reference]);
        $this->postJson($url, ['scenario' => 'unknown'])->assertUnprocessable()->assertJsonValidationErrors('scenario');
        $this->actingAs($other)->post($start)->assertNotFound();
        $this->get($url)->assertNotFound();
        $this->post($url, ['scenario' => 'success'])->assertNotFound();
        $this->post(route('orders.demo-payment.simulate', [$otherOrder->order_number, $session->reference]), ['scenario' => 'success'])
            ->assertNotFound();
        $this->assertNull($session->refresh()->result);

        $this->app->instance('env', 'production');
        $this->actingAs($owner)->post($start, [], ['Sec-Fetch-Site' => 'same-origin'])->assertNotFound();
        $this->get($url)->assertNotFound();
        $this->postCallback($this->event($session))->assertNotFound();
    }

    private function simulateWithoutCallback(User $owner, Order $order): DemoPaymentSession
    {
        $this->actingAs($owner)->post(route('orders.demo-payment.start', $order->order_number))->assertStatus(303);
        $session = $order->payment()->sole()->demoSessions()->sole();
        $this->post(route('orders.demo-payment.simulate', [$order->order_number, $session->reference]), ['scenario' => 'no-callback'])
            ->assertStatus(303);

        return $session->refresh();
    }

    private function order(User $owner): Order
    {
        $order = $owner->orders()->create([
            'order_number' => 'CF-'.Str::upper(Str::random(16)), 'checkout_key' => Str::uuid()->toString(),
            'request_hash' => hash('sha256', 'demo'), 'recipient_name' => 'Khách thử', 'phone' => '0901234567',
            'fulfillment_type' => 'pickup', 'status' => 'pending', 'subtotal_vnd' => 42000,
            'discount_vnd' => 0, 'delivery_fee_vnd' => 0, 'total_vnd' => 42000,
        ]);
        $order->payment()->create(['method' => 'demo_gateway', 'status' => 'pending', 'amount_vnd' => 42000]);

        return $order;
    }

    /** @param array<string, mixed> $overrides */
    private function event(DemoPaymentSession $session, array $overrides = []): string
    {
        return json_encode([
            'provider' => 'demo', 'event_id' => 'demo:'.$session->reference, 'reference' => $session->reference,
            'result' => $session->result, 'amount_vnd' => $session->amount_vnd, 'currency' => 'VND', ...$overrides,
        ], JSON_THROW_ON_ERROR);
    }

    private function postCallback(string $body, ?string $signature = null): TestResponse
    {
        $key = hash_hmac('sha256', 'coffee-shop:demo-payment:v1', config('app.key'), true);

        return $this->call('POST', route('payments.demo.callback'), [], [], [], [
            'HTTP_ACCEPT' => 'application/json',
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_DEMO_SIGNATURE' => $signature ?? hash_hmac('sha256', $body, $key),
        ], $body);
    }
}
