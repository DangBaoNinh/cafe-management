<?php

namespace Tests\Feature;

use App\Enums\FulfillmentType;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\UserRole;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

class OrderHandoffTest extends TestCase
{
    use RefreshDatabase;

    private const DISPATCH = [
        'fulfillment_type' => 'delivery', 'method' => 'cod', 'expected_status' => 'ready',
        'target_status' => 'out_for_delivery', 'confirmed' => '1',
    ];

    private const PICKUP = [
        'fulfillment_type' => 'pickup', 'method' => 'cod', 'expected_status' => 'ready',
        'target_status' => 'completed', 'confirmed' => '1', 'cod_amount_vnd' => '40000', 'collection_method' => 'cash',
    ];

    public function test_pickup_completes_directly_and_records_cod_and_order_at_the_same_time(): void
    {
        $staff = User::factory()->create(['role' => UserRole::Staff]);
        $customer = User::factory()->create();
        $order = $this->order($customer, status: OrderStatus::Preparing);
        $queue = route('operations.handoff.index');
        $show = route('operations.orders.show', $order->order_number);
        $action = route('operations.orders.handoff', $order->order_number);

        $this->actingAs($staff)->get($queue)->assertOk()->assertSee('Chưa có đơn cần bàn giao.');
        $this->post(route('operations.orders.prepare', $order->order_number), [
            'expected_status' => 'preparing', 'target_status' => 'ready',
        ])->assertStatus(303);
        $this->get($queue)->assertOk()->assertSee($order->order_number);
        $this->get($show)->assertOk()->assertSee('Số tiền COD đã thu (VND)')->assertSee('Hoàn tất bàn giao')
            ->assertDontSee('Đánh dấu đang giao');
        $this->postJson($action, [...self::DISPATCH, 'fulfillment_type' => 'pickup'])->assertConflict();

        $this->freezeSecond();
        $this->post($action, self::PICKUP)->assertStatus(303)->assertRedirectToRoute('operations.invoices.orders.show', $order->order_number);
        $this->get($show)->assertOk()->assertSee('Hoàn tất lúc')->assertDontSee($action);
        $this->get($queue)->assertOk()->assertDontSee($order->order_number);
        $this->postJson($action, self::PICKUP)->assertConflict();

        $payment = $order->payment()->sole();
        $this->assertSame(OrderStatus::Completed, $order->refresh()->status);
        $this->assertSame(PaymentStatus::Paid, $payment->status);
        $this->assertEquals(now(), $order->completed_at);
        $this->assertEquals($order->completed_at, $payment->paid_at);
        $this->assertNull($payment->collection_reference);
        $this->assertSame('ready', $order->statusHistories()->where('to_status', 'completed')->sole()->from_status);
        $this->assertSame($staff->id, $order->statusHistories()->where('to_status', 'completed')->sole()->actor_id);
        $this->assertSame($staff->id, $payment->statusHistories()->where('to_status', 'paid')->sole()->actor_id);
        $this->assertFalse($order->statusHistories()->where('to_status', 'out_for_delivery')->exists());
        $this->actingAs($customer)->get(route('orders.show', $order->order_number))
            ->assertOk()->assertSee('Đã hoàn tất')->assertSee('Đã thanh toán')->assertDontSee('Đang giao');
    }

    public function test_delivery_requires_dispatch_and_keeps_out_for_delivery_until_handoff_succeeds(): void
    {
        $staff = User::factory()->create(['role' => UserRole::Staff]);
        $customer = User::factory()->create();
        $order = $this->order($customer, FulfillmentType::Delivery);
        $show = route('operations.orders.show', $order->order_number);
        $action = route('operations.orders.handoff', $order->order_number);
        $complete = [...self::PICKUP, 'fulfillment_type' => 'delivery', 'expected_status' => 'out_for_delivery'];

        $this->actingAs($staff)->get($show)->assertOk()->assertSee('Đánh dấu đang giao')->assertDontSee('Hoàn tất bàn giao');
        $this->postJson($action, [...$complete, 'expected_status' => 'ready'])->assertConflict();
        $this->postJson($action, [...self::DISPATCH, 'cod_amount_vnd' => 40000])
            ->assertUnprocessable()->assertJsonValidationErrors('cod_amount_vnd');
        $this->post($action, self::DISPATCH)->assertStatus(303);
        $this->postJson($action, self::DISPATCH)->assertConflict();
        $this->get($show)->assertOk()->assertSee('Nếu chưa giao được')->assertSee('Hoàn tất bàn giao');
        $this->assertSame(OrderStatus::OutForDelivery, $order->refresh()->status);
        $this->assertNull($order->completed_at);
        $this->assertSame(PaymentStatus::Pending, $order->payment()->sole()->status);

        $this->postJson($action, [...$complete, 'confirmed' => '0'])->assertUnprocessable()->assertJsonValidationErrors('confirmed');
        $this->postJson($action, [...$complete, 'cod_amount_vnd' => 39999])->assertUnprocessable()->assertJsonValidationErrors('cod_amount_vnd');
        $this->assertSame(OrderStatus::OutForDelivery, $order->refresh()->status);
        $this->assertSame(1, $order->payment()->sole()->statusHistories()->count());
        $this->post($action, $complete)->assertStatus(303);
        $this->assertSame(OrderStatus::Completed, $order->refresh()->status);
        $this->assertSame(PaymentStatus::Paid, $order->payment()->sole()->status);
        $this->actingAs($customer)->get(route('orders.show', $order->order_number))->assertOk()
            ->assertSeeInOrder(['Tiến trình đơn', 'Đang giao', 'Đã hoàn tất']);
    }

    public function test_bank_transfer_requires_paid_status_and_keeps_the_original_collection(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $customer = User::factory()->create();
        $unpaid = $this->order($customer, FulfillmentType::Delivery, method: 'bank_transfer');
        $this->actingAs($admin)->postJson(route('operations.orders.handoff', $unpaid->order_number), [
            ...self::DISPATCH, 'method' => 'bank_transfer',
        ])->assertConflict()->assertJsonValidationErrors('payment');
        $unpaidPickup = $this->order($customer, method: 'bank_transfer');
        $transferCompletion = [...self::PICKUP, 'method' => 'bank_transfer'];
        unset($transferCompletion['cod_amount_vnd']);
        $this->postJson(route('operations.orders.handoff', $unpaidPickup->order_number), $transferCompletion)
            ->assertConflict()->assertJsonValidationErrors('payment');
        $this->assertSame(OrderStatus::Ready, $unpaid->refresh()->status);
        $this->assertSame(OrderStatus::Ready, $unpaidPickup->refresh()->status);

        $paid = $this->order($customer, status: OrderStatus::Confirmed, method: 'bank_transfer');
        $this->freezeSecond();
        $this->post(route('operations.payments.reconcile', $paid->order_number), [
            'method' => 'bank_transfer', 'expected_status' => 'pending', 'amount_vnd' => 40000, 'collection_reference' => 'BANK-HANDOFF',
        ])->assertStatus(303);
        $prepare = route('operations.orders.prepare', $paid->order_number);
        $this->post($prepare, ['expected_status' => 'confirmed', 'target_status' => 'preparing'])->assertStatus(303);
        $this->post($prepare, ['expected_status' => 'preparing', 'target_status' => 'ready'])->assertStatus(303);
        $this->travel(2)->minutes();
        $action = route('operations.orders.handoff', $paid->order_number);
        $this->postJson($action, [...$transferCompletion, 'cod_amount_vnd' => 40000])
            ->assertUnprocessable()->assertJsonValidationErrors('cod_amount_vnd');
        $this->post($action, $transferCompletion)->assertStatus(303);
        $payment = $paid->payment()->sole();
        $this->assertEquals(now()->subMinutes(2), $payment->paid_at);
        $this->assertEquals(now(), $paid->refresh()->completed_at);
        $this->assertSame('BANK-HANDOFF', $payment->collection_reference);
        $this->assertSame(2, $payment->statusHistories()->count());
    }

    public function test_handoff_rejects_mismatched_methods_and_unauthorized_actors(): void
    {
        $staff = User::factory()->create(['role' => UserRole::Staff]);
        $customer = User::factory()->create();
        $order = $this->order($customer);
        $queue = route('operations.handoff.index');
        $action = route('operations.orders.handoff', $order->order_number);
        $this->get($queue)->assertRedirectToRoute('login');
        $this->post($action, self::PICKUP)->assertRedirectToRoute('login');
        foreach ([$customer, User::factory()->create(['role' => UserRole::Staff, 'is_active' => false])] as $actor) {
            $this->actingAs($actor)->get($queue)->assertForbidden();
            $this->postJson($action, self::PICKUP)->assertForbidden();
        }
        $this->actingAs($staff)->get(route('dashboard'))->assertRedirectToRoute('operations.orders.index');
        $this->get(route('operations.orders.index'))->assertOk()->assertSee($queue);
        $this->postJson($action, [...self::PICKUP, 'fulfillment_type' => 'delivery'])
            ->assertConflict()->assertJsonValidationErrors('fulfillment_type');
        $this->postJson($action, [...self::PICKUP, 'method' => 'bank_transfer', 'cod_amount_vnd' => null])
            ->assertConflict()->assertJsonValidationErrors('method');
        $this->postJson($action, [...self::PICKUP, 'cod_amount_vnd' => null])
            ->assertUnprocessable()->assertJsonValidationErrors('cod_amount_vnd');
        $this->postJson(route('operations.orders.handoff', 'CF-MISSING'), self::PICKUP)->assertNotFound();
        $this->assertSame(OrderStatus::Ready, $order->refresh()->status);
    }

    public function test_cod_collection_is_rolled_back_if_order_history_cannot_be_written(): void
    {
        $staff = User::factory()->create(['role' => UserRole::Staff]);
        $order = $this->order(User::factory()->create());
        OrderStatusHistory::creating(static function (): never {
            throw new RuntimeException('Order history write failed');
        });
        $this->actingAs($staff)->post(route('operations.orders.handoff', $order->order_number), self::PICKUP)->assertServerError();
        $payment = $order->payment()->sole();
        $this->assertSame(OrderStatus::Ready, $order->refresh()->status);
        $this->assertNull($order->completed_at);
        $this->assertSame(PaymentStatus::Pending, $payment->status);
        $this->assertNull($payment->paid_at);
        $this->assertNull($payment->collection_method);
        $this->assertSame(1, $payment->statusHistories()->count());
        $this->assertSame(1, $order->statusHistories()->count());
    }

    public function test_zero_total_cod_records_an_explicit_zero_amount(): void
    {
        $staff = User::factory()->create(['role' => UserRole::Staff]);
        $order = $this->order(User::factory()->create(), amount: 0);
        $this->actingAs($staff)->post(route('operations.orders.handoff', $order->order_number), [
            ...self::PICKUP, 'cod_amount_vnd' => '0',
        ])->assertStatus(303);
        $this->assertSame(OrderStatus::Completed, $order->refresh()->status);
        $payment = $order->payment()->sole();
        $this->assertSame(PaymentStatus::Paid, $payment->status);
        $this->assertSame(0, $payment->amount_vnd);
    }

    public function test_completed_handoff_tab_is_separate_and_keeps_its_pagination_filter(): void
    {
        $staff = User::factory()->create(['role' => UserRole::Staff]);
        $owner = User::factory()->create();
        $active = $this->order($owner);
        $completed = [];
        for ($i = 0; $i < 16; $i++) {
            $order = $this->order($owner, status: OrderStatus::Completed);
            $order->completed_at = now()->subMinutes($i);
            $order->save();
            $completed[] = $order;
        }
        $this->actingAs($staff)->get(route('operations.handoff.index'))->assertOk()
            ->assertSee($active->order_number)->assertDontSee($completed[0]->order_number);
        $this->get(route('operations.handoff.index', ['view' => 'completed']))->assertOk()
            ->assertSee($completed[0]->order_number)->assertDontSee($active->order_number)
            ->assertSee(route('operations.invoices.orders.show', $completed[0]->order_number))
            ->assertSee('view=completed')->assertSee('page=2')->assertViewHas('orders', fn ($orders) => $orders->total() === 16);
        $this->get(route('operations.handoff.index', ['view' => 'completed', 'page' => 2]))->assertOk()->assertSee($completed[15]->order_number);
        $this->getJson(route('operations.handoff.index', ['view' => 'invalid']))->assertUnprocessable();
    }

    public function test_cash_and_transfer_handoffs_issue_paid_invoices_and_preserve_checkout_method(): void
    {
        $this->actingAs(User::factory()->create(['role' => UserRole::Staff]));
        foreach (['cash' => 'Tiền mặt', 'bank_transfer' => 'Chuyển khoản'] as $method => $label) {
            $order = $this->order(User::factory()->create());
            $action = route('operations.orders.handoff', $order->order_number);
            $invoice = route('operations.invoices.orders.show', $order->order_number);
            $this->post($action, [...self::PICKUP, 'collection_method' => $method])->assertRedirect($invoice);
            $payment = $order->payment()->sole();
            $this->assertSame('cod', $payment->method->value);
            $this->assertSame($method, $payment->collection_method->value);
            $this->assertSame('paid', $payment->status->value);
            $this->get($invoice)->assertOk()->assertSee('Đã thanh toán')->assertSee($label)->assertSee('In hóa đơn');
            $this->get(route('operations.invoices.history'))->assertOk()->assertSee($order->order_number)->assertSee('Thực thu: '.$label);
            $this->postJson($action, [...self::PICKUP, 'collection_method' => $method])->assertConflict();
            $this->assertSame(1, $payment->statusHistories()->where('to_status', 'paid')->count());
        }
    }

    public function test_handoff_requires_a_valid_collection_choice_without_writing_payment(): void
    {
        $order = $this->order(User::factory()->create());
        $this->actingAs(User::factory()->create(['role' => UserRole::Staff]));
        foreach ([null, '', 'card', ['cash']] as $method) {
            $this->postJson(route('operations.orders.handoff', $order->order_number), [...self::PICKUP, 'collection_method' => $method])
                ->assertUnprocessable()->assertJsonValidationErrors('collection_method');
        }
        $this->assertSame('ready', $order->refresh()->status->value);
        $this->assertSame('pending', $order->payment()->sole()->status->value);
        $this->assertNull($order->payment()->sole()->collection_method);
    }

    private function order(
        User $owner,
        FulfillmentType $fulfillment = FulfillmentType::Pickup,
        OrderStatus $status = OrderStatus::Ready,
        string $method = 'cod',
        int $amount = 40000,
    ): Order {
        $order = $owner->orders()->create([
            'order_number' => 'CF-'.Str::ulid(), 'checkout_key' => Str::uuid()->toString(),
            'request_hash' => hash('sha256', 'checkout'), 'recipient_name' => 'Khách nhận', 'phone' => '0901234567',
            'fulfillment_type' => $fulfillment, 'status' => $status, 'subtotal_vnd' => 40000,
            'discount_vnd' => 40000 - $amount, 'delivery_fee_vnd' => 0, 'total_vnd' => $amount,
        ]);
        $order->statusHistories()->create(['actor_id' => $owner->id, 'to_status' => $status->value]);
        $payment = $order->payment()->create(['method' => $method, 'status' => PaymentStatus::Pending, 'amount_vnd' => $amount]);
        $payment->statusHistories()->create(['actor_id' => $owner->id, 'to_status' => 'pending']);

        return $order;
    }
}
