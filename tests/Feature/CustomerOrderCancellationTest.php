<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\UserRole;
use App\Models\Order;
use App\Models\PaymentStatusHistory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

class CustomerOrderCancellationTest extends TestCase
{
    use RefreshDatabase;

    private const CANCELLATION = ['expected_status' => 'pending', 'confirm' => '1'];

    public function test_owner_cancels_unpaid_order_once_and_sees_it_in_finished_orders(): void
    {
        $owner = User::factory()->create();
        $order = $this->order($owner);
        $show = route('orders.show', $order->order_number);
        $cancel = route('orders.cancel', $order->order_number);

        $this->actingAs($owner)->get($show)->assertOk()->assertSee($cancel)
            ->assertSee('name="_token"', escape: false)
            ->assertSee('name="expected_status" value="pending"', escape: false)
            ->assertSee('name="confirm"', escape: false);

        $reason = 'Giờ nhận hàng thay đổi';
        $this->post($cancel, [...self::CANCELLATION, 'reason' => $reason])
            ->assertStatus(303)->assertRedirect($show);
        $this->get($show)->assertOk()->assertSee('Đã hủy đơn hàng.')->assertSee('Đã hủy thanh toán')
            ->assertDontSee($cancel)->assertDontSee($reason);
        $this->get(route('orders.index', ['status' => 'finished']))->assertOk()->assertSee($order->order_number);
        $this->get(route('orders.index', ['status' => 'processing']))->assertOk()->assertDontSee($order->order_number);

        $this->postJson($cancel, self::CANCELLATION)->assertConflict()->assertJsonValidationErrors('expected_status');
        $this->assertSame(OrderStatus::Cancelled, $order->refresh()->status);
        $payment = $order->payment()->sole();
        $this->assertSame(PaymentStatus::Voided, $payment->status);
        $this->assertDatabaseHas('order_status_histories', [
            'order_id' => $order->id, 'actor_id' => $owner->id,
            'from_status' => 'pending', 'to_status' => 'cancelled', 'reason' => $reason,
        ]);
        $this->assertDatabaseHas('payment_status_histories', [
            'payment_id' => $payment->id, 'actor_id' => $owner->id,
            'from_status' => 'pending', 'to_status' => 'voided', 'reason' => $reason,
        ]);
        $this->assertSame(2, $order->statusHistories()->count());
        $this->assertSame(2, $payment->statusHistories()->count());
    }

    public function test_paid_transfer_requires_a_manual_refund_and_keeps_the_original_receipt(): void
    {
        $owner = User::factory()->create();
        $order = $this->order($owner, method: 'bank_transfer');
        $payment = $order->payment()->sole();
        $payment->forceFill([
            'status' => PaymentStatus::Paid, 'paid_at' => now(), 'collection_reference' => 'CHUYEN-RIENG',
        ])->save();
        $payment->statusHistories()->create(['from_status' => 'pending', 'to_status' => 'paid']);

        $this->actingAs($owner)->post(route('orders.cancel', $order->order_number), self::CANCELLATION)->assertStatus(303);
        $this->get(route('orders.show', $order->order_number))->assertOk()->assertSee('Chờ hoàn tiền')
            ->assertSee('Vui lòng liên hệ cửa hàng')->assertDontSee('CHUYEN-RIENG');
        $this->assertSame(OrderStatus::Cancelled, $order->refresh()->status);
        $this->assertSame(PaymentStatus::RefundPending, $payment->refresh()->status);
        $this->assertNotNull($payment->paid_at);
        $this->assertSame('CHUYEN-RIENG', $payment->collection_reference);
        $this->assertNull($payment->refunded_at);
        $this->assertNull($payment->refund_reference);
        $this->assertDatabaseHas('payment_status_histories', [
            'payment_id' => $payment->id, 'actor_id' => $owner->id, 'from_status' => 'paid', 'to_status' => 'refund_pending',
        ]);
        $this->postJson(route('orders.cancel', $order->order_number), self::CANCELLATION)->assertConflict();
        $this->assertSame(3, $payment->statusHistories()->count());
    }

    public function test_cancelling_an_unpaid_transfer_hides_old_transfer_instructions(): void
    {
        $owner = User::factory()->create();
        $order = $this->order($owner, method: 'bank_transfer');
        $order->payment()->sole()->update(['transfer_details' => [
            'bank_name' => 'Ngân hàng thử', 'account_number' => '123456789', 'account_holder' => 'Cửa hàng thử',
        ]]);
        $confirmation = route('checkout.confirmation', $order->order_number);
        $this->actingAs($owner)->get($confirmation)->assertOk()->assertSee('123456789');

        $this->post(route('orders.cancel', $order->order_number), self::CANCELLATION)->assertStatus(303);
        $this->get($confirmation)->assertOk()->assertDontSee('123456789');
        $this->get(route('orders.show', $order->order_number))->assertOk()
            ->assertSee('Đã hủy thanh toán')->assertDontSee('Xem hướng dẫn chuyển khoản');
    }

    public function test_cancellation_enforces_access_and_checks_current_status_and_form_fields(): void
    {
        $owner = User::factory()->create();
        $order = $this->order($owner);
        $cancel = route('orders.cancel', $order->order_number);
        $show = route('orders.show', $order->order_number);

        $this->post($cancel, self::CANCELLATION)->assertRedirectToRoute('login');
        foreach ([['role' => UserRole::Staff], ['role' => UserRole::Admin], ['is_active' => false]] as $attributes) {
            $this->actingAs(User::factory()->create($attributes))->postJson($cancel, self::CANCELLATION)->assertForbidden();
        }
        $this->actingAs(User::factory()->create())->postJson($cancel, self::CANCELLATION)->assertNotFound();
        $this->actingAs($owner)->postJson(route('orders.cancel', 'CF-MISSING'), self::CANCELLATION)->assertNotFound();
        $this->postJson($cancel, [])->assertUnprocessable()->assertJsonValidationErrors(['confirm', 'expected_status']);
        $this->postJson($cancel, [...self::CANCELLATION, 'expected_status' => 'confirmed', 'reason' => str_repeat('a', 501)])
            ->assertUnprocessable()->assertJsonValidationErrors(['expected_status', 'reason']);

        $this->get($show)->assertSee($cancel);
        foreach (OrderStatus::cases() as $status) {
            if ($status === OrderStatus::Pending) {
                continue;
            }
            $order->update(['status' => $status]);
            $this->postJson($cancel, self::CANCELLATION)->assertConflict();
            $this->assertSame($status, $order->refresh()->status);
        }
        $order->update(['status' => OrderStatus::Confirmed]);
        $this->post($cancel, self::CANCELLATION)->assertRedirect($show);
        $this->get($show)->assertOk()->assertSee('Đơn đã đổi trạng thái.')->assertDontSee($cancel);
        $this->assertSame(PaymentStatus::Pending, $order->payment()->sole()->status);
        $this->assertSame(1, $order->statusHistories()->count());
        $this->assertSame(1, $order->payment()->sole()->statusHistories()->count());
    }

    public function test_a_failure_writing_the_payment_history_rolls_back_the_entire_cancellation(): void
    {
        $owner = User::factory()->create();
        $order = $this->order($owner);
        $cancel = route('orders.cancel', $order->order_number);
        PaymentStatusHistory::creating(static function (): never {
            throw new RuntimeException('Payment history write failed');
        });

        $this->actingAs($owner)->post($cancel, self::CANCELLATION)->assertServerError();
        $this->assertSame(OrderStatus::Pending, $order->refresh()->status);
        $this->assertSame(PaymentStatus::Pending, $order->payment()->sole()->status);
        $this->assertSame(1, $order->statusHistories()->count());
        $this->assertSame(1, $order->payment()->sole()->statusHistories()->count());
    }

    private function order(User $owner, string $method = 'cod'): Order
    {
        $order = $owner->orders()->create([
            'order_number' => 'CF-'.Str::ulid(), 'checkout_key' => Str::uuid()->toString(),
            'request_hash' => hash('sha256', 'checkout'), 'recipient_name' => 'Khách nhận', 'phone' => '0901234567',
            'fulfillment_type' => 'pickup', 'status' => OrderStatus::Pending, 'subtotal_vnd' => 40000,
            'discount_vnd' => 0, 'delivery_fee_vnd' => 0, 'total_vnd' => 40000,
        ]);
        $payment = $order->payment()->create(['method' => $method, 'status' => PaymentStatus::Pending, 'amount_vnd' => 40000]);
        $order->statusHistories()->create(['actor_id' => $owner->id, 'to_status' => 'pending']);
        $payment->statusHistories()->create(['actor_id' => $owner->id, 'to_status' => 'pending']);

        return $order;
    }
}
