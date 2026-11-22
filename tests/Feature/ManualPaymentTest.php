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

class ManualPaymentTest extends TestCase
{
    use RefreshDatabase;

    private const TRANSFER = [
        'method' => 'bank_transfer', 'expected_status' => 'pending', 'amount_vnd' => '40000',
        'collection_reference' => 'BANK-001', 'note' => 'Ghi chú nội bộ khi thu',
    ];

    private const REFUND = [
        'method' => 'bank_transfer', 'expected_status' => 'refund_pending',
        'refund_reference' => 'REFUND-001', 'note' => 'Ghi chú nội bộ khi hoàn',
    ];

    public function test_queue_and_payment_details_require_an_active_operator(): void
    {
        $customer = User::factory()->create();
        $transfer = $this->order($customer);
        $cod = $this->order($customer, method: 'cod');
        $refund = $this->order($customer, OrderStatus::Rejected, PaymentStatus::RefundPending);
        $closed = $this->order($customer, OrderStatus::Cancelled, PaymentStatus::Voided);
        $index = route('operations.payments.index');
        $detail = route('operations.payments.show', $transfer->order_number);
        $reconcile = route('operations.payments.reconcile', $transfer->order_number);

        $this->get($index)->assertRedirectToRoute('login');
        foreach ([$customer, User::factory()->create(['role' => UserRole::Staff, 'is_active' => false])] as $user) {
            $this->actingAs($user)->get($index)->assertForbidden();
            $this->get($detail)->assertForbidden();
            $this->postJson($reconcile, self::TRANSFER)->assertForbidden();
        }

        $staff = User::factory()->create(['role' => UserRole::Staff]);
        $this->actingAs($staff)->get(route('dashboard'))->assertRedirectToRoute('operations.orders.index');
        $this->get(route('operations.orders.index'))->assertOk()->assertSee($index);
        $this->get($index)->assertOk()->assertSee($transfer->order_number)->assertSee($refund->order_number)
            ->assertDontSee($cod->order_number)->assertDontSee($closed->order_number);
        $this->get(route('operations.orders.show', $transfer->order_number))->assertSee($detail);
        $this->get($detail)->assertOk()->assertSee($reconcile)->assertSee('name="_token"', escape: false);
        $this->get(route('operations.payments.show', $cod->order_number))->assertOk()
            ->assertSee('Ghi nhận tiền thu khi bàn giao')->assertDontSee('Xác nhận chuyển khoản');
        $this->get(route('operations.payments.show', 'CF-MISSING'))->assertNotFound();
    }

    public function test_matching_transfers_are_recorded_once_and_details_remain_internal(): void
    {
        $staff = User::factory()->create(['role' => UserRole::Staff]);
        $customer = User::factory()->create();
        $this->freezeSecond();

        foreach ([OrderStatus::Pending, OrderStatus::Confirmed] as $state) {
            $order = $this->order($customer, $state);
            $details = route('operations.payments.show', $order->order_number);
            $reconcile = route('operations.payments.reconcile', $order->order_number);
            $request = [...self::TRANSFER, 'collection_reference' => 'BANK-'.$state->value];
            $this->actingAs($staff)->post($reconcile, $request)->assertStatus(303)->assertRedirect($details);
            $this->get($details)->assertOk()->assertSee($request['collection_reference'])->assertSee('Đã thanh toán')
                ->assertDontSee($reconcile);
            $this->get(route('operations.payments.index'))->assertDontSee($order->order_number);
            $this->postJson($reconcile, $request)->assertConflict();

            $payment = $order->payment()->sole();
            $this->assertSame($state, $order->refresh()->status);
            $this->assertSame(PaymentStatus::Paid, $payment->status);
            $this->assertSame($request['collection_reference'], $payment->collection_reference);
            $this->assertEquals(now(), $payment->paid_at);
            $history = $payment->statusHistories()->where('to_status', 'paid')->sole();
            $this->assertSame($staff->id, $history->actor_id);
            $this->assertSame('pending', $history->from_status);
            $this->assertSame($request['note'], $history->reason);
            $this->assertEquals(now(), $history->created_at);
            $this->assertSame(2, $payment->statusHistories()->count());

            $this->actingAs($customer)->get(route('orders.show', $order->order_number))->assertOk()
                ->assertSee('Đã thanh toán')->assertDontSee($request['collection_reference'])->assertDontSee($request['note']);
        }
    }

    public function test_mismatched_missing_or_duplicate_transfer_data_does_not_change_payment(): void
    {
        $staff = User::factory()->create(['role' => UserRole::Staff]);
        $customer = User::factory()->create();
        $first = $this->order($customer);
        $second = $this->order($customer);
        $cod = $this->order($customer, method: 'cod');
        $cancelled = $this->order($customer, OrderStatus::Cancelled, PaymentStatus::Voided);
        $reconcile = route('operations.payments.reconcile', $second->order_number);

        $this->actingAs($staff)->post(route('operations.payments.reconcile', $first->order_number), self::TRANSFER)->assertStatus(303);
        $this->postJson($reconcile, self::TRANSFER)->assertUnprocessable()->assertJsonValidationErrors('collection_reference');
        $this->postJson($reconcile, [...self::TRANSFER, 'collection_reference' => 'NEW', 'amount_vnd' => 39999])
            ->assertUnprocessable()->assertJsonValidationErrors('amount_vnd');
        $this->postJson($reconcile, [...self::TRANSFER, 'collection_reference' => '  '])
            ->assertUnprocessable()->assertJsonValidationErrors('collection_reference');
        $this->postJson($reconcile, [...self::TRANSFER, 'amount_vnd' => 0, 'collection_reference' => null])
            ->assertUnprocessable()->assertJsonValidationErrors('amount_vnd');
        $this->postJson($reconcile, [...self::TRANSFER, 'expected_status' => 'paid'])
            ->assertUnprocessable()->assertJsonValidationErrors('expected_status');
        $this->postJson($reconcile, [...self::TRANSFER, 'method' => 'cod'])
            ->assertUnprocessable()->assertJsonValidationErrors('method');
        $this->postJson(route('operations.payments.reconcile', $cod->order_number), self::TRANSFER)->assertConflict();
        $this->postJson(route('operations.payments.reconcile', $cancelled->order_number), self::TRANSFER)->assertConflict();
        $this->postJson(route('operations.payments.reconcile', 'CF-MISSING'), self::TRANSFER)->assertNotFound();

        foreach ([$second, $cod, $cancelled] as $order) {
            $payment = $order->payment()->sole();
            $this->assertSame($order === $cancelled ? PaymentStatus::Voided : PaymentStatus::Pending, $payment->status);
            $this->assertNull($payment->collection_reference);
            $this->assertNull($payment->paid_at);
            $this->assertSame(1, $payment->statusHistories()->count());
        }
    }

    public function test_admin_records_only_a_pending_refund_with_its_reference(): void
    {
        $staff = User::factory()->create(['role' => UserRole::Staff]);
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $customer = User::factory()->create();
        $order = $this->order($customer, OrderStatus::Rejected, PaymentStatus::RefundPending);
        $payment = $order->payment()->sole();
        $payment->forceFill(['collection_reference' => 'BANK-REFUND', 'paid_at' => now()->subDay()])->save();
        $show = route('operations.payments.show', $order->order_number);
        $refund = route('operations.payments.refund', $order->order_number);
        $this->freezeSecond();

        $this->actingAs($staff)->get($show)->assertOk()->assertDontSee($refund);
        $this->postJson($refund, self::REFUND)->assertForbidden();
        $this->actingAs($customer)->postJson($refund, self::REFUND)->assertForbidden();
        $this->actingAs($admin)->get($show)->assertOk()->assertSee($refund);
        $this->postJson($refund, [...self::REFUND, 'refund_reference' => ' '])
            ->assertUnprocessable()->assertJsonValidationErrors('refund_reference');
        $this->postJson($refund, [...self::REFUND, 'method' => 'cod'])->assertConflict();
        $this->assertSame(PaymentStatus::RefundPending, $payment->refresh()->status);
        $this->post($refund, self::REFUND)->assertStatus(303)->assertRedirect($show);
        $this->get($show)->assertOk()->assertSee('Đã hoàn tiền')->assertSee(self::REFUND['refund_reference'])->assertDontSee($refund);
        $this->postJson($refund, self::REFUND)->assertConflict();
        $this->assertSame(OrderStatus::Rejected, $order->refresh()->status);
        $this->assertSame(PaymentStatus::Refunded, $payment->refresh()->status);
        $this->assertSame('BANK-REFUND', $payment->collection_reference);
        $this->assertSame(self::REFUND['refund_reference'], $payment->refund_reference);
        $this->assertEquals(now(), $payment->refunded_at);
        $history = $payment->statusHistories()->where('to_status', 'refunded')->sole();
        $this->assertSame($admin->id, $history->actor_id);
        $this->assertSame(self::REFUND['note'], $history->reason);
        $this->actingAs($customer)->get(route('orders.show', $order->order_number))->assertOk()
            ->assertSee('Đã hoàn tiền')->assertDontSee(self::REFUND['refund_reference'])->assertDontSee(self::REFUND['note']);
    }

    public function test_zero_total_can_be_confirmed_without_a_reference_and_cancelled_without_a_refund(): void
    {
        $staff = User::factory()->create(['role' => UserRole::Staff]);
        $customer = User::factory()->create();
        $order = $this->order($customer, amount: 0);
        $show = route('operations.payments.show', $order->order_number);
        $reconcile = route('operations.payments.reconcile', $order->order_number);
        $request = [...self::TRANSFER, 'amount_vnd' => '0', 'note' => null];
        $this->actingAs($staff)->get($show)->assertOk()->assertSee('Xác nhận đơn không phát sinh khoản thu.');
        $this->postJson($reconcile, $request)->assertUnprocessable()->assertJsonValidationErrors('collection_reference');
        $this->post($reconcile, [...$request, 'collection_reference' => null])->assertStatus(303);
        $payment = $order->payment()->sole();
        $this->assertSame(PaymentStatus::Paid, $payment->status);
        $this->assertNull($payment->collection_reference);
        $this->assertNotNull($payment->statusHistories()->where('to_status', 'paid')->sole()->reason);

        $this->actingAs($customer)->post(route('orders.cancel', $order->order_number), [
            'expected_status' => 'pending', 'confirm' => '1',
        ])->assertStatus(303);
        $this->assertSame(PaymentStatus::Voided, $payment->refresh()->status);
        $this->assertNull($payment->refund_reference);
    }

    public function test_failed_history_write_rolls_back_payment_and_reference(): void
    {
        $operator = User::factory()->create(['role' => UserRole::Admin]);
        $customer = User::factory()->create();
        $collection = $this->order($customer);
        $refund = $this->order($customer, OrderStatus::Cancelled, PaymentStatus::RefundPending);
        PaymentStatusHistory::creating(static function (): never {
            throw new RuntimeException('Payment history write failed');
        });
        $this->actingAs($operator)->post(route('operations.payments.reconcile', $collection->order_number), self::TRANSFER)
            ->assertServerError();
        $this->post(route('operations.payments.refund', $refund->order_number), self::REFUND)->assertServerError();
        $collected = $collection->payment()->sole();
        $refunded = $refund->payment()->sole();
        $this->assertSame(PaymentStatus::Pending, $collected->status);
        $this->assertNull($collected->collection_reference);
        $this->assertNull($collected->paid_at);
        $this->assertSame(1, $collected->statusHistories()->count());
        $this->assertSame(PaymentStatus::RefundPending, $refunded->status);
        $this->assertNull($refunded->refund_reference);
        $this->assertNull($refunded->refunded_at);
        $this->assertSame(1, $refunded->statusHistories()->count());
    }

    private function order(
        User $owner,
        OrderStatus $status = OrderStatus::Pending,
        PaymentStatus $paymentStatus = PaymentStatus::Pending,
        string $method = 'bank_transfer',
        int $amount = 40000,
    ): Order {
        $order = $owner->orders()->create([
            'order_number' => 'CF-'.Str::ulid(), 'checkout_key' => Str::uuid()->toString(),
            'request_hash' => hash('sha256', 'checkout'), 'recipient_name' => 'Khách nhận', 'phone' => '0901234567',
            'fulfillment_type' => 'pickup', 'status' => $status, 'subtotal_vnd' => 40000,
            'discount_vnd' => 40000 - $amount, 'delivery_fee_vnd' => 0, 'total_vnd' => $amount,
        ]);
        $payment = $order->payment()->create(['method' => $method, 'status' => $paymentStatus, 'amount_vnd' => $amount]);
        $payment->statusHistories()->create(['actor_id' => $owner->id, 'to_status' => $paymentStatus->value]);

        return $order;
    }
}
