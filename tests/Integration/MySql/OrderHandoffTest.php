<?php

namespace Tests\Integration\MySql;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class OrderHandoffTest extends MySqlTestCase
{
    public function test_delivery_cod_is_collected_once_and_only_records_payment_on_completion(): void
    {
        DB::setDefaultConnection($this->primary->getName());
        $this->primary->beginTransaction();

        try {
            $customer = User::factory()->create();
            $staff = User::factory()->create(['role' => UserRole::Staff]);
            $number = 'CF-'.Str::ulid();
            $order = $customer->orders()->create([
                'order_number' => $number, 'checkout_key' => Str::uuid()->toString(),
                'request_hash' => hash('sha256', $number), 'recipient_name' => 'Khách thử', 'phone' => '0901234567',
                'fulfillment_type' => 'delivery', 'status' => 'ready', 'subtotal_vnd' => 40000,
                'discount_vnd' => 0, 'delivery_fee_vnd' => 12000, 'total_vnd' => 52000,
            ]);
            $payment = $order->payment()->create(['method' => 'cod', 'status' => 'pending', 'amount_vnd' => 52000]);
            $action = route('operations.orders.handoff', $number);
            $input = ['fulfillment_type' => 'delivery', 'method' => 'cod', 'confirmed' => '1'];

            $this->actingAs($staff)->post($action, [
                ...$input, 'expected_status' => 'ready', 'target_status' => 'out_for_delivery',
            ])->assertStatus(303);
            $this->assertSame('pending', $payment->refresh()->status->value);
            $this->assertNull($order->refresh()->completed_at);
            $complete = [...$input, 'expected_status' => 'out_for_delivery', 'target_status' => 'completed', 'cod_amount_vnd' => '52000', 'collection_method' => 'cash'];
            $this->postJson($action, [...$complete, 'cod_amount_vnd' => '40000'])
                ->assertUnprocessable()->assertJsonValidationErrors('cod_amount_vnd');
            $this->post($action, $complete)->assertStatus(303);
            $this->postJson($action, $complete)->assertConflict();

            $this->assertSame('completed', $order->refresh()->status->value);
            $this->assertSame('paid', $payment->refresh()->status->value);
            $this->assertNotNull($order->completed_at);
            $this->assertEquals($order->completed_at, $payment->paid_at);
            $this->assertSame(['out_for_delivery', 'completed'], $order->statusHistories()->orderBy('id')->pluck('to_status')->all());
            $this->assertSame('paid', $payment->statusHistories()->sole()->to_status);
        } finally {
            $this->primary->rollBack();
        }
    }
}
