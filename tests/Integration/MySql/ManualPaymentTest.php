<?php

namespace Tests\Integration\MySql;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ManualPaymentTest extends MySqlTestCase
{
    public function test_transfer_references_are_unique_and_a_cancelled_collection_can_be_recorded_as_refunded(): void
    {
        DB::setDefaultConnection($this->primary->getName());
        // This case verifies the manual refund path, rather than automatic demo refunds.
        config(['checkout.customer_refund_demo' => false]);
        $this->primary->beginTransaction();

        try {
            $customer = User::factory()->create();
            $customer->forceFill(['linked_bank' => [
                'bank_name' => 'Ngân hàng thử', 'account_number' => '0123456789', 'account_holder' => 'KHACH THU',
            ]])->save();
            $staff = User::factory()->create(['role' => UserRole::Staff]);
            $admin = User::factory()->create(['role' => UserRole::Admin]);
            $orders = [];
            for ($i = 0; $i < 2; $i++) {
                $number = 'CF-'.Str::ulid();
                $order = $customer->orders()->create([
                    'order_number' => $number, 'checkout_key' => Str::uuid()->toString(),
                    'request_hash' => hash('sha256', $number), 'recipient_name' => 'Khách thử', 'phone' => '0901234567',
                    'fulfillment_type' => 'pickup', 'status' => 'pending', 'subtotal_vnd' => 40000,
                    'discount_vnd' => 0, 'delivery_fee_vnd' => 0, 'total_vnd' => 40000,
                ]);
                $order->payment()->create(['method' => 'bank_transfer', 'status' => 'pending', 'amount_vnd' => 40000]);
                $orders[] = $order;
            }

            [$first, $second] = $orders;
            $reference = 'BANK-'.strtoupper(Str::random(16));
            $transfer = ['method' => 'bank_transfer', 'expected_status' => 'pending', 'amount_vnd' => 40000, 'collection_reference' => $reference];
            $this->actingAs($staff)->post(route('operations.payments.reconcile', $first->order_number), $transfer)->assertStatus(303);
            $this->postJson(route('operations.payments.reconcile', $second->order_number), [
                ...$transfer, 'collection_reference' => strtolower($reference),
            ])->assertUnprocessable()->assertJsonValidationErrors('collection_reference');

            $rejectedPayment = $second->payment()->sole();
            $this->assertSame('pending', $rejectedPayment->status->value);
            $this->assertNull($rejectedPayment->collection_reference);
            $this->assertSame(0, $rejectedPayment->statusHistories()->count());
            $this->actingAs($customer)->post(route('orders.cancel', $first->order_number), [
                'expected_status' => 'pending', 'confirm' => '1',
            ])->assertStatus(303);
            $payment = $first->payment()->sole();
            $this->assertSame('refund_pending', $payment->status->value);

            $this->actingAs($admin)->post(route('operations.payments.refund', $first->order_number), [
                'method' => 'bank_transfer', 'expected_status' => 'refund_pending', 'refund_reference' => 'RE-'.$reference,
            ])->assertStatus(303);
            $this->assertSame('refunded', $payment->refresh()->status->value);
            $this->assertSame($reference, $payment->collection_reference);
            $this->assertSame('RE-'.$reference, $payment->refund_reference);
            $this->assertSame(3, $payment->statusHistories()->count());

            $this->actingAs($staff)->post(route('operations.orders.decide', $second->order_number), [
                'expected_status' => 'pending', 'decision' => 'confirmed',
            ])->assertStatus(303);
            $prepare = route('operations.orders.prepare', $second->order_number);
            $start = ['expected_status' => 'confirmed', 'target_status' => 'preparing'];
            $this->postJson($prepare, $start)->assertConflict()->assertJsonValidationErrors('payment');
            $this->post(route('operations.payments.reconcile', $second->order_number), [
                ...$transfer, 'collection_reference' => $reference.'-2',
            ])->assertStatus(303);
            $this->post($prepare, $start)->assertStatus(303);
            $this->post($prepare, ['expected_status' => 'preparing', 'target_status' => 'ready'])->assertStatus(303);
            $this->assertSame('ready', $second->refresh()->status->value);
            $this->assertSame('paid', $rejectedPayment->refresh()->status->value);
            $this->assertSame(['confirmed', 'preparing', 'ready'], $second->statusHistories()->orderBy('id')->pluck('to_status')->all());
        } finally {
            $this->primary->rollBack();
        }
    }
}
