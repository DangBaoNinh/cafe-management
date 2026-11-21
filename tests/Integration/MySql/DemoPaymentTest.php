<?php

namespace Tests\Integration\MySql;

use App\Enums\PaymentStatus;
use App\Enums\UserRole;
use App\Models\User;
use App\Services\DemoPaymentGateway;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DemoPaymentTest extends MySqlTestCase
{
    public function test_demo_payment_reuses_session_and_recovers_a_missing_callback_once(): void
    {
        DB::setDefaultConnection($this->primary->getName());
        $this->primary->beginTransaction();

        try {
            $owner = User::factory()->create();
            $admin = User::factory()->create(['role' => UserRole::Admin]);
            $number = 'CF-'.Str::upper(Str::random(16));
            $order = $owner->orders()->create([
                'order_number' => $number, 'checkout_key' => Str::uuid()->toString(),
                'request_hash' => hash('sha256', $number), 'recipient_name' => 'Khách thử', 'phone' => '0901234567',
                'fulfillment_type' => 'delivery', 'status' => 'confirmed', 'subtotal_vnd' => 40000,
                'discount_vnd' => 0, 'delivery_fee_vnd' => 2000, 'total_vnd' => 42000,
            ]);
            $payment = $order->payment()->create(['method' => 'demo_gateway', 'status' => 'pending', 'amount_vnd' => 42000]);
            $gateway = app(DemoPaymentGateway::class);
            $session = $gateway->start($owner, $number);
            $this->assertSame($session->id, $gateway->start($owner, $number)->id);
            $gateway->simulate($owner, $number, $session->reference, 'succeeded', false);
            $this->assertSame(PaymentStatus::Pending, $payment->refresh()->status);
            $this->assertFalse($payment->allowsPreparation());

            $url = route('operations.payments.demo-reconcile', $number);
            $this->actingAs($admin)->post($url)->assertStatus(303);
            $this->post($url)->assertStatus(303);
            $this->assertSame(PaymentStatus::Paid, $payment->refresh()->status);
            $this->assertTrue($payment->allowsPreparation());
            $this->assertTrue($payment->allowsHandoff());
            $this->assertSame($admin->id, $payment->statusHistories()->sole()->actor_id);
            $this->assertNotNull($session->refresh()->applied_at);
        } finally {
            $this->primary->rollBack();
        }
    }
}
