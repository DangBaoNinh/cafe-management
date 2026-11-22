<?php

namespace Tests\Feature;

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

class OrderPreparationTest extends TestCase
{
    use RefreshDatabase;

    private const START = ['expected_status' => 'confirmed', 'target_status' => 'preparing'];

    private const READY = ['expected_status' => 'preparing', 'target_status' => 'ready'];

    public function test_cod_can_start_before_collection_and_the_owner_can_see_both_new_milestones(): void
    {
        $staff = User::factory()->create(['role' => UserRole::Staff]);
        $customer = User::factory()->create();
        $order = $this->order($customer, OrderStatus::Pending);
        $show = route('operations.orders.show', $order->order_number);
        $prepare = route('operations.orders.prepare', $order->order_number);
        $queue = route('operations.preparation.index');
        $this->freezeSecond();

        $this->actingAs($staff)->post(route('operations.orders.decide', $order->order_number), [
            'expected_status' => 'pending', 'decision' => 'confirmed',
        ])->assertStatus(303);
        $this->get($queue)->assertOk()->assertSee($order->order_number)->assertSee('Đã xác nhận');
        $this->get($show)->assertOk()->assertSee('Bắt đầu pha chế')->assertDontSee('Đánh dấu sẵn sàng')
            ->assertSee('name="_token"', escape: false);
        $this->post($prepare, self::START)->assertStatus(303)->assertRedirect($show);
        $this->get($show)->assertOk()->assertSee('Đã bắt đầu pha chế.')->assertSee('Đánh dấu sẵn sàng');
        $this->get($queue)->assertOk()->assertSee($order->order_number)->assertSee('Đang pha chế');
        $this->postJson($prepare, self::START)->assertConflict()->assertJsonValidationErrors('expected_status');
        $this->actingAs($customer)->get(route('orders.show', $order->order_number))->assertOk()->assertSee('Đang pha chế');

        $this->travel(2)->minutes();
        $this->actingAs($staff)->post($prepare, self::READY)->assertStatus(303)->assertRedirect($show);
        $this->get($show)->assertOk()->assertSee('Đã đánh dấu đơn sẵn sàng.')->assertDontSee($prepare);
        $this->get($queue)->assertOk()->assertSee('Chưa có đơn cần pha chế.')->assertDontSee($order->order_number);
        $this->postJson($prepare, self::READY)->assertConflict()->assertJsonValidationErrors('expected_status');
        $this->assertSame(OrderStatus::Ready, $order->refresh()->status);
        $this->assertSame(PaymentStatus::Pending, $order->payment()->sole()->status);
        $this->assertSame(0, $order->payment()->sole()->statusHistories()->count());
        $histories = $order->statusHistories()->whereIn('to_status', ['preparing', 'ready'])->orderBy('id')->get();
        $this->assertSame(['confirmed', 'preparing'], $histories->pluck('from_status')->all());
        $this->assertSame(['preparing', 'ready'], $histories->pluck('to_status')->all());
        $this->assertSame([$staff->id, $staff->id], $histories->pluck('actor_id')->all());
        $this->assertEquals(now()->subMinutes(2), $histories[0]->created_at);
        $this->assertEquals(now(), $histories[1]->created_at);
        $this->actingAs($customer)->get(route('orders.show', $order->order_number))->assertOk()
            ->assertSeeInOrder(['Tiến trình đơn', 'Đã xác nhận', 'Đang pha chế', 'Đã chuẩn bị xong']);
        $this->actingAs(User::factory()->create())->get(route('orders.show', $order->order_number))->assertNotFound();
    }

    public function test_bank_transfer_can_start_only_after_uc20_confirms_payment(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $order = $this->order(User::factory()->create(), method: 'bank_transfer');
        $show = route('operations.orders.show', $order->order_number);
        $prepare = route('operations.orders.prepare', $order->order_number);
        $this->actingAs($admin)->get($show)->assertOk()->assertSee('Đối chiếu chuyển khoản')->assertDontSee('Bắt đầu pha chế');
        $this->postJson($prepare, self::START)->assertConflict()->assertJsonValidationErrors('payment');
        $this->assertSame(OrderStatus::Confirmed, $order->refresh()->status);
        $this->assertSame(1, $order->statusHistories()->count());

        $this->post(route('operations.payments.reconcile', $order->order_number), [
            'method' => 'bank_transfer', 'expected_status' => 'pending', 'amount_vnd' => 40000,
            'collection_reference' => 'CF-PREP-TRANSFER',
        ])->assertStatus(303);
        $this->get($show)->assertOk()->assertSee('Bắt đầu pha chế');
        $this->post($prepare, self::START)->assertStatus(303);
        $this->assertSame(OrderStatus::Preparing, $order->refresh()->status);
        $this->post($prepare, self::READY)->assertStatus(303);
        $this->assertSame(OrderStatus::Ready, $order->refresh()->status);
        $this->assertSame(PaymentStatus::Paid, $order->payment()->sole()->status);
    }

    public function test_an_operator_cannot_skip_milestones_or_prepare_a_cancelled_order(): void
    {
        $staff = User::factory()->create(['role' => UserRole::Staff]);
        $customer = User::factory()->create();
        $confirmed = $this->order($customer);
        $pending = $this->order($customer, OrderStatus::Pending);
        $cancelled = $this->order($customer, OrderStatus::Pending);
        $this->actingAs($customer)->post(route('orders.cancel', $cancelled->order_number), [
            'expected_status' => 'pending', 'confirm' => '1',
        ])->assertStatus(303);

        $this->actingAs($staff);
        $prepare = route('operations.orders.prepare', $confirmed->order_number);
        $this->postJson($prepare, [...self::START, 'target_status' => 'ready'])->assertConflict();
        $this->postJson($prepare, self::READY)->assertConflict();
        $this->postJson($prepare, [...self::START, 'target_status' => 'completed'])
            ->assertUnprocessable()->assertJsonValidationErrors('target_status');
        $this->postJson($prepare, ['target_status' => 'preparing'])->assertUnprocessable()->assertJsonValidationErrors('expected_status');
        $this->postJson(route('operations.orders.prepare', $pending->order_number), self::START)->assertConflict();
        $this->postJson(route('operations.orders.prepare', $cancelled->order_number), self::START)->assertConflict();
        $this->postJson(route('operations.orders.prepare', 'CF-MISSING'), self::START)->assertNotFound();
        $this->assertSame(['confirmed', 'pending', 'cancelled'], [$confirmed->refresh()->status->value, $pending->refresh()->status->value, $cancelled->refresh()->status->value]);
        $this->assertSame(1, $confirmed->statusHistories()->count());
        $this->assertSame(1, $pending->statusHistories()->count());
        $this->assertSame(2, $cancelled->statusHistories()->count());
    }

    public function test_queue_and_action_require_an_active_operator(): void
    {
        $customer = User::factory()->create();
        $order = $this->order($customer);
        $queue = route('operations.preparation.index');
        $prepare = route('operations.orders.prepare', $order->order_number);
        $this->get($queue)->assertRedirectToRoute('login');
        $this->post($prepare, self::START)->assertRedirectToRoute('login');
        foreach ([$customer, User::factory()->create(['role' => UserRole::Staff, 'is_active' => false])] as $actor) {
            $this->actingAs($actor)->get($queue)->assertForbidden();
            $this->postJson($prepare, self::START)->assertForbidden();
        }

        $this->actingAs(User::factory()->create(['role' => UserRole::Staff]))->get(route('dashboard'))->assertRedirectToRoute('operations.orders.index');
        $this->get(route('operations.orders.index'))->assertOk()->assertSee($queue);
        $this->assertSame(OrderStatus::Confirmed, $order->refresh()->status);
    }

    public function test_history_write_failure_rolls_back_the_order_transition(): void
    {
        $staff = User::factory()->create(['role' => UserRole::Staff]);
        $order = $this->order(User::factory()->create());
        OrderStatusHistory::creating(static function (): never {
            throw new RuntimeException('Order history write failed');
        });
        $this->actingAs($staff)->post(route('operations.orders.prepare', $order->order_number), self::START)->assertServerError();
        $this->assertSame(OrderStatus::Confirmed, $order->refresh()->status);
        $this->assertSame(1, $order->statusHistories()->count());
    }

    public function test_quick_completion_from_queue_preserves_payment_and_rejects_stale_requests(): void
    {
        $staff = User::factory()->create(['role' => UserRole::Staff]);
        $order = $this->order(User::factory()->create(), OrderStatus::Preparing);
        $action = route('operations.orders.prepare', $order->order_number);
        $this->actingAs($staff)->get(route('operations.preparation.index'))->assertOk()
            ->assertSee('Đã hoàn tất')->assertSee($action)->assertSee('name="return_to"', false);
        $this->post($action, [...self::READY, 'return_to' => 'preparation'])
            ->assertStatus(303)->assertRedirectToRoute('operations.preparation.index');
        $this->assertSame(OrderStatus::Ready, $order->refresh()->status);
        $this->assertSame(PaymentStatus::Pending, $order->payment()->sole()->status);
        $this->assertSame(1, $order->statusHistories()->where('to_status', 'ready')->count());
        $this->postJson($action, [...self::READY, 'return_to' => 'preparation'])->assertConflict();
        $this->assertSame(1, $order->statusHistories()->where('to_status', 'ready')->count());
        $this->get(route('operations.preparation.index'))->assertDontSee($order->order_number);
        $this->get(route('operations.handoff.index'))->assertSee($order->order_number);
    }

    public function test_quick_completion_cannot_skip_preparation_or_supply_an_external_redirect(): void
    {
        $order = $this->order(User::factory()->create());
        $action = route('operations.orders.prepare', $order->order_number);
        $this->actingAs(User::factory()->create(['role' => UserRole::Staff]))
            ->postJson($action, ['expected_status' => 'confirmed', 'target_status' => 'ready', 'return_to' => 'preparation'])->assertConflict();
        $this->postJson($action, [...self::START, 'return_to' => 'https://example.test'])->assertUnprocessable();
        $this->assertSame(OrderStatus::Confirmed, $order->refresh()->status);
    }

    private function order(User $owner, OrderStatus $status = OrderStatus::Confirmed, string $method = 'cod'): Order
    {
        $order = $owner->orders()->create([
            'order_number' => 'CF-'.Str::ulid(), 'checkout_key' => Str::uuid()->toString(),
            'request_hash' => hash('sha256', 'checkout'), 'recipient_name' => 'Khách nhận', 'phone' => '0901234567',
            'fulfillment_type' => 'pickup', 'status' => $status, 'subtotal_vnd' => 40000,
            'discount_vnd' => 0, 'delivery_fee_vnd' => 0, 'total_vnd' => 40000,
        ]);
        $order->statusHistories()->create(['actor_id' => $owner->id, 'to_status' => $status->value]);
        $order->payment()->create(['method' => $method, 'status' => PaymentStatus::Pending, 'amount_vnd' => 40000]);

        return $order;
    }
}
