<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\UserRole;
use App\Models\Category;
use App\Models\Order;
use App\Models\PaymentStatusHistory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

class OrderIntakeTest extends TestCase
{
    use RefreshDatabase;

    private const CONFIRM = ['decision' => 'confirmed', 'expected_status' => 'pending'];

    private const REJECT = ['decision' => 'rejected', 'expected_status' => 'pending', 'reason' => 'Hết nguyên liệu'];

    public function test_only_active_staff_and_admin_can_access_the_queue_detail_and_decision(): void
    {
        $customer = User::factory()->create();
        $order = $this->order($customer);
        $index = route('operations.orders.index');
        $show = route('operations.orders.show', $order->order_number);
        $decide = route('operations.orders.decide', $order->order_number);
        $this->get($index)->assertRedirectToRoute('login');
        $this->get($show)->assertRedirectToRoute('login');
        $this->post($decide, self::CONFIRM)->assertRedirectToRoute('login');

        foreach ([$customer, User::factory()->create(['role' => UserRole::Staff, 'is_active' => false])] as $user) {
            $this->actingAs($user)->get($index)->assertForbidden();
            $this->get($show)->assertForbidden();
            $this->postJson($decide, self::CONFIRM)->assertForbidden();
        }

        $staff = User::factory()->create(['role' => UserRole::Staff]);
        $this->actingAs($staff)->get(route('dashboard'))->assertRedirectToRoute('operations.table-sessions.index');
        $this->get($index)->assertOk()->assertDontSee(route('admin.accounts.index'));
        $this->get($show)->assertOk()->assertSee($decide);
        $this->get(route('admin.dashboard'))->assertForbidden();
        $this->get(route('operations.orders.show', 'CF-MISSING'))->assertNotFound();
        $this->postJson(route('operations.orders.decide', 'CF-MISSING'), self::CONFIRM)->assertNotFound();

        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk()->assertSee($index);
        $this->get($index)->assertOk()->assertSee(route('admin.accounts.index'));
        $this->post($decide, self::CONFIRM)->assertStatus(303);
        $this->assertSame(OrderStatus::Confirmed, $order->refresh()->status);
    }

    public function test_queue_only_lists_pending_orders_oldest_first_and_paginates(): void
    {
        $staff = User::factory()->create(['role' => UserRole::Staff]);
        $customer = User::factory()->create();
        $index = route('operations.orders.index');
        $this->actingAs($staff)->get($index)->assertOk()->assertSee('Chưa có đơn chờ xác nhận.');
        $this->freezeTime();
        $first = $this->order($customer);
        $second = $this->order($customer);
        $exclude = $this->order($customer);
        $exclude->update(['status' => OrderStatus::Confirmed]);
        $this->travel(1)->minutes();
        for ($i = 0; $i < 14; $i++) {
            $last = $this->order($customer);
        }
        $this->get($index)->assertOk()->assertSeeInOrder([$first->order_number, $second->order_number])
            ->assertDontSee($exclude->order_number)->assertDontSee($last->order_number);
        $this->get($index.'?page=2')->assertOk()->assertSee($last->order_number)->assertDontSee($first->order_number);
    }

    public function test_staff_reviews_snapshots_and_confirms_even_before_transfer_is_collected(): void
    {
        $staff = User::factory()->create(['role' => UserRole::Staff]);
        $customer = User::factory()->create();
        $order = $this->order($customer, 'bank_transfer');
        $order->update(['fulfillment_type' => 'delivery', 'address_snapshot' => ['address_line' => '10 Nguyễn Huệ']]);
        $category = Category::create(['name' => 'Cà phê', 'slug' => 'coffee']);
        $product = $category->products()->create(['name' => 'Tên hiện tại', 'slug' => 'coffee']);
        $size = $product->sizes()->create(['code' => 'm', 'label' => 'Cỡ hiện tại', 'price_vnd' => 60000]);
        $order->items()->create([
            'product_size_id' => $size->id, 'product_name' => 'Tên lúc đặt', 'size_label' => 'Cỡ lúc đặt',
            'ice_level' => 'less', 'quantity' => 1, 'base_unit_price_vnd' => 40000, 'line_total_vnd' => 40000,
        ]);

        $show = route('operations.orders.show', $order->order_number);
        $decide = route('operations.orders.decide', $order->order_number);
        $this->actingAs($staff)->get($show)->assertOk()->assertSee('10 Nguyễn Huệ')->assertSee('Khách nhận')
            ->assertSee('0901234567')->assertSee('Tên lúc đặt')->assertSee('Cỡ lúc đặt')->assertSee('40.000 ₫')
            ->assertDontSee('Tên hiện tại')->assertSee('Chờ thanh toán');
        $this->post($decide, self::CONFIRM)->assertStatus(303)->assertRedirect($show);
        $this->get($show)->assertOk()->assertSee('Đã xác nhận đơn hàng.')->assertDontSee($decide);
        $this->get(route('operations.orders.index'))->assertDontSee($order->order_number);
        $this->post($decide, self::CONFIRM)->assertRedirect($show);
        $this->get($show)->assertSee('Đơn đã đổi trạng thái.');
        $this->actingAs($customer)->postJson(route('orders.cancel', $order->order_number), [
            'expected_status' => 'pending', 'confirm' => '1',
        ])->assertConflict();

        $this->assertSame(OrderStatus::Confirmed, $order->refresh()->status);
        $payment = $order->payment()->sole();
        $this->assertSame(PaymentStatus::Pending, $payment->status);
        $this->assertSame(1, $payment->statusHistories()->count());
        $history = $order->statusHistories()->where('to_status', 'confirmed')->sole();
        $this->assertSame($staff->id, $history->actor_id);
        $this->assertSame('pending', $history->from_status);
        $this->assertNotNull($history->created_at);
        $this->assertSame(2, $order->statusHistories()->count());
    }

    public function test_rejection_requires_a_reason_and_transitions_paid_and_unpaid_payments(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $customer = User::factory()->create();
        $this->actingAs($admin);
        foreach (['pending' => 'voided', 'paid' => 'refund_pending'] as $before => $after) {
            $order = $this->order($customer, $before === 'paid' ? 'bank_transfer' : 'cod');
            $payment = $order->payment()->sole();
            $payment->update(['status' => $before]);
            $decide = route('operations.orders.decide', $order->order_number);
            $show = route('operations.orders.show', $order->order_number);
            $this->postJson($decide, [...self::REJECT, 'reason' => '   '])
                ->assertUnprocessable()->assertJsonValidationErrors('reason');
            $this->postJson($decide, [...self::REJECT, 'reason' => str_repeat('a', 501)])
                ->assertUnprocessable()->assertJsonValidationErrors('reason');
            $this->postJson($decide, [...self::CONFIRM, 'decision' => 'cancelled', 'expected_status' => 'confirmed'])
                ->assertUnprocessable()->assertJsonValidationErrors(['decision', 'expected_status']);
            $this->assertSame(OrderStatus::Pending, $order->refresh()->status);
            $this->assertSame($before, $payment->refresh()->status->value);
            $this->post($decide, self::REJECT)->assertStatus(303)->assertRedirect($show);
            $this->get($show)->assertOk()->assertSee('Đã từ chối đơn hàng.')->assertDontSee($decide);
            $this->assertSame(OrderStatus::Rejected, $order->refresh()->status);
            $this->assertSame($after, $payment->refresh()->status->value);
            $this->assertDatabaseHas('order_status_histories', [
                'order_id' => $order->id, 'actor_id' => $admin->id, 'from_status' => 'pending',
                'to_status' => 'rejected', 'reason' => self::REJECT['reason'],
            ]);
            $this->assertDatabaseHas('payment_status_histories', [
                'payment_id' => $payment->id, 'actor_id' => $admin->id,
                'from_status' => $before, 'to_status' => $after, 'reason' => self::REJECT['reason'],
            ]);
            $this->postJson($decide, self::REJECT)->assertConflict();
            $this->assertSame(2, $order->statusHistories()->count());
            $this->assertSame(2, $payment->statusHistories()->count());
        }

        $this->actingAs($customer)->get(route('orders.show', $order->order_number))
            ->assertOk()->assertSee('Chờ hoàn tiền')->assertDontSee(self::REJECT['reason']);
    }

    public function test_rejection_rolls_back_if_payment_history_cannot_be_written(): void
    {
        $staff = User::factory()->create(['role' => UserRole::Staff]);
        $order = $this->order(User::factory()->create());
        PaymentStatusHistory::creating(static function (): never {
            throw new RuntimeException('Payment history write failed');
        });
        $this->actingAs($staff)->post(route('operations.orders.decide', $order->order_number), self::REJECT)->assertServerError();
        $this->assertSame(OrderStatus::Pending, $order->refresh()->status);
        $payment = $order->payment()->sole();
        $this->assertSame(PaymentStatus::Pending, $payment->status);
        $this->assertSame(1, $order->statusHistories()->count());
        $this->assertSame(1, $payment->statusHistories()->count());
    }

    private function order(User $customer, string $method = 'cod'): Order
    {
        $order = $customer->orders()->create([
            'order_number' => 'CF-'.Str::ulid(), 'checkout_key' => Str::uuid()->toString(),
            'request_hash' => hash('sha256', 'checkout'), 'recipient_name' => 'Khách nhận', 'phone' => '0901234567',
            'fulfillment_type' => 'pickup', 'status' => OrderStatus::Pending, 'subtotal_vnd' => 40000,
            'discount_vnd' => 0, 'delivery_fee_vnd' => 0, 'total_vnd' => 40000,
        ]);
        $payment = $order->payment()->create(['method' => $method, 'status' => PaymentStatus::Pending, 'amount_vnd' => 40000]);
        $order->statusHistories()->create(['actor_id' => $customer->id, 'to_status' => 'pending']);
        $payment->statusHistories()->create(['actor_id' => $customer->id, 'to_status' => 'pending']);

        return $order;
    }
}
