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

class AdminOrderManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_combined_filters_respect_inclusive_vietnamese_calendar_dates(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $customer = User::factory()->create();
        foreach ([
            ['CF-MATCH-BEFORE', '2026-11-20 16:59:59', OrderStatus::Ready, 'bank_transfer'],
            ['CF-MATCH-START', '2026-11-20 17:00:00', OrderStatus::Ready, 'bank_transfer'],
            ['CF-MATCH-END', '2026-11-21 16:59:59', OrderStatus::Ready, 'bank_transfer'],
            ['CF-MATCH-AFTER', '2026-11-21 17:00:00', OrderStatus::Ready, 'bank_transfer'],
            ['CF-MATCH-COD', '2026-11-21 02:00:00', OrderStatus::Ready, 'cod'],
            ['CF-MATCH-PENDING', '2026-11-21 02:00:00', OrderStatus::Pending, 'bank_transfer'],
            ['CF-OTHER', '2026-11-21 02:00:00', OrderStatus::Ready, 'bank_transfer'],
        ] as [$number, $utc, $status, $method]) {
            $this->order($customer, $number, $status, $method)->forceFill(['created_at' => $utc])->save();
        }
        $index = route('admin.orders.index');
        $filter = ['number' => 'cf-match-', 'from' => '2026-11-21', 'to' => '2026-11-21', 'status' => 'ready', 'method' => 'bank_transfer'];
        $this->actingAs($admin)->get(route('admin.dashboard'))->assertSee($index);
        $this->get($index)->assertOk()->assertSee('CF-OTHER');
        $this->get(route('admin.orders.index', $filter))->assertOk()
            ->assertSeeInOrder(['CF-MATCH-END', '23:59 21/11/2026', 'CF-MATCH-START', '00:00 21/11/2026'])
            ->assertDontSee('CF-MATCH-BEFORE')->assertDontSee('CF-MATCH-AFTER')
            ->assertDontSee('CF-MATCH-COD')->assertDontSee('CF-MATCH-PENDING')->assertDontSee('CF-OTHER');
        $this->get(route('admin.orders.index', ['to' => '2026-11-21']))->assertOk()
            ->assertSee('CF-MATCH-BEFORE')->assertDontSee('CF-MATCH-AFTER');
        $this->getJson(route('admin.orders.index', ['from' => '2026-11-22', 'to' => '2026-11-21', 'number' => 'CF_%', 'method' => 'wrong']))
            ->assertUnprocessable()->assertJsonValidationErrors(['to', 'number', 'method']);
        $this->getJson(route('admin.orders.index', ['from' => '2026-02-30', 'status' => ['ready']]))
            ->assertUnprocessable()->assertJsonValidationErrors(['from', 'status']);
    }

    public function test_pagination_keeps_filters_and_uses_stable_ordering(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $customer = User::factory()->create();
        $this->freezeSecond();
        foreach (range(1, 16) as $number) {
            $this->order($customer, sprintf('CF-PAGE-%02d', $number));
        }

        $filters = ['number' => 'CF-PAGE-', 'status' => 'ready', 'method' => 'cod'];
        $response = $this->actingAs($admin)->get(route('admin.orders.index', $filters))->assertOk()
            ->assertSeeInOrder(['CF-PAGE-16', 'CF-PAGE-02'])->assertDontSee('CF-PAGE-01');
        $this->assertSame(16, $response->viewData('orders')->total());
        parse_str(parse_url($response->viewData('orders')->nextPageUrl(), PHP_URL_QUERY), $query);
        $this->assertSame([...$filters, 'page' => '2'], $query);
        $this->get(route('admin.orders.index', $query))->assertOk()->assertSee('CF-PAGE-01')->assertDontSee('CF-PAGE-16');
    }

    public function test_only_active_admins_can_list_show_or_cancel_in_admin_area(): void
    {
        $customer = User::factory()->create();
        $order = $this->order($customer, 'CF-ROLE');
        $index = route('admin.orders.index');
        $show = route('admin.orders.show', $order->order_number);
        $cancel = route('admin.orders.cancel', $order->order_number);
        $input = ['expected_status' => 'ready', 'reason' => 'Cần hủy'];
        $this->get($index)->assertRedirectToRoute('login');
        $this->get($show)->assertRedirectToRoute('login');
        $this->post($cancel, $input)->assertRedirectToRoute('login');
        foreach ([$customer, User::factory()->create(['role' => UserRole::Staff]), User::factory()->create(['role' => UserRole::Admin, 'is_active' => false])] as $actor) {
            $this->actingAs($actor)->get($index)->assertForbidden();
            $this->get($show)->assertForbidden();
            $this->postJson($cancel, $input)->assertForbidden();
        }
        $this->actingAs(User::factory()->create(['role' => UserRole::Admin]))
            ->get(route('admin.orders.show', 'CF-MISSING'))->assertNotFound();
        $this->postJson(route('admin.orders.cancel', 'CF-MISSING'), $input)->assertNotFound();
        $this->assertSame(OrderStatus::Ready, $order->refresh()->status);
    }

    public function test_admin_cancels_a_paid_ready_order_and_the_two_audit_histories_remain_internal(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin, 'name' => 'Quản trị ca tối']);
        $customer = User::factory()->create();
        $order = $this->order($customer, 'CF-PAID-READY', OrderStatus::Confirmed, 'bank_transfer');
        $order->update(['fulfillment_type' => 'delivery', 'address_snapshot' => ['address_line' => '10 Nguyễn Huệ']]);
        $this->actingAs($admin)->post(route('operations.payments.reconcile', $order->order_number), [
            'method' => 'bank_transfer', 'expected_status' => 'pending', 'amount_vnd' => '40000',
            'collection_reference' => 'BANK-REF-UC21', 'note' => 'Ghi chú đối chiếu riêng',
        ])->assertStatus(303);
        $prepare = route('operations.orders.prepare', $order->order_number);
        $this->post($prepare, ['expected_status' => 'confirmed', 'target_status' => 'preparing'])->assertStatus(303);
        $this->post($prepare, ['expected_status' => 'preparing', 'target_status' => 'ready'])->assertStatus(303);
        $show = route('admin.orders.show', $order->order_number);
        $cancel = route('admin.orders.cancel', $order->order_number);
        $this->get($show)->assertOk()->assertSee('10 Nguyễn Huệ')->assertSee('Lịch sử đơn hàng')
            ->assertSee('Lịch sử thanh toán')->assertSee('Ghi chú đối chiếu riêng')->assertSee($cancel)
            ->assertSee('name="_token"', escape: false);

        $this->freezeSecond();
        $this->postJson($cancel, ['expected_status' => 'ready', 'reason' => ' '])
            ->assertUnprocessable()->assertJsonValidationErrors('reason');
        $this->postJson($cancel, ['expected_status' => 'ready', 'reason' => str_repeat('a', 501)])
            ->assertUnprocessable()->assertJsonValidationErrors('reason');
        $this->assertSame(OrderStatus::Ready, $order->refresh()->status);
        $reason = 'Hết nguyên liệu <kiểm tra>';
        $input = ['expected_status' => 'ready', 'reason' => $reason];
        $this->post($cancel, $input)->assertStatus(303)->assertRedirect($show);
        $this->postJson($cancel, $input)->assertConflict()->assertJsonValidationErrors('expected_status');
        $payment = $order->payment()->sole();
        $this->assertSame(OrderStatus::Cancelled, $order->refresh()->status);
        $this->assertSame(PaymentStatus::RefundPending, $payment->status);
        $this->assertSame('BANK-REF-UC21', $payment->collection_reference);
        $this->assertNull($payment->refund_reference);
        $this->assertNull($payment->refunded_at);
        $orderHistory = $order->statusHistories()->where('to_status', 'cancelled')->sole();
        $paymentHistory = $payment->statusHistories()->where('to_status', 'refund_pending')->sole();
        $this->assertSame('ready', $orderHistory->from_status);
        $this->assertSame('paid', $paymentHistory->from_status);
        foreach ([$orderHistory, $paymentHistory] as $history) {
            $this->assertSame($admin->id, $history->actor_id);
            $this->assertSame($reason, $history->reason);
            $this->assertEquals(now(), $history->created_at);
        }
        $this->get($show)->assertOk()->assertSee('Đã hủy')->assertSee('Chờ hoàn tiền')->assertSee('Ghi nhận hoàn tiền')
            ->assertSee('Quản trị ca tối')->assertSee($reason)->assertDontSee($reason, escape: false)->assertDontSee($cancel);
        $this->actingAs($customer)->get(route('orders.show', $order->order_number))
            ->assertOk()->assertSee('Chờ hoàn tiền')->assertDontSee($reason)->assertDontSee('Quản trị ca tối');
    }

    public function test_cancel_checks_current_status_and_remains_closed_to_shipping_or_terminal_orders(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $customer = User::factory()->create();
        $this->actingAs($admin);
        foreach ([OrderStatus::Pending, OrderStatus::Confirmed, OrderStatus::Preparing] as $status) {
            $order = $this->order($customer, 'CF-CANCEL-'.$status->value, $status);
            $cancel = route('admin.orders.cancel', $order->order_number);
            $this->postJson($cancel, ['expected_status' => 'ready', 'reason' => 'Phiên cũ'])->assertConflict();
            $this->assertSame($status, $order->refresh()->status);
            $this->post($cancel, ['expected_status' => $status->value, 'reason' => 'Không thể phục vụ'])->assertStatus(303);
            $this->assertSame(OrderStatus::Cancelled, $order->refresh()->status);
            $this->assertSame(PaymentStatus::Voided, $order->payment()->sole()->status);
        }

        foreach ([OrderStatus::OutForDelivery, OrderStatus::Completed, OrderStatus::Cancelled, OrderStatus::Rejected] as $status) {
            $order = $this->order($customer, 'CF-CLOSED-'.$status->value, $status);
            $order->update(['fulfillment_type' => 'delivery']);
            $cancel = route('admin.orders.cancel', $order->order_number);
            $before = $order->payment()->sole()->status;
            $this->get(route('admin.orders.show', $order->order_number))->assertOk()->assertDontSee($cancel);
            $this->postJson($cancel, ['expected_status' => $status->value, 'reason' => 'Thử hủy'])->assertConflict();
            $this->assertSame($status, $order->refresh()->status);
            $this->assertSame($before, $order->payment()->sole()->status);
            $this->assertSame(1, $order->statusHistories()->count());
            $this->assertSame(1, $order->payment()->sole()->statusHistories()->count());
        }
    }

    public function test_cancel_rolls_back_when_payment_history_cannot_be_written(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $order = $this->order(User::factory()->create(), 'CF-ROLLBACK');
        PaymentStatusHistory::creating(static function (): never {
            throw new RuntimeException('Payment history write failed');
        });
        $this->actingAs($admin)->post(route('admin.orders.cancel', $order->order_number), [
            'expected_status' => 'ready', 'reason' => 'Thử hoàn tác',
        ])->assertServerError();
        $payment = $order->payment()->sole();
        $this->assertSame(OrderStatus::Ready, $order->refresh()->status);
        $this->assertSame(PaymentStatus::Pending, $payment->status);
        $this->assertSame(1, $payment->statusHistories()->count());
        $this->assertSame(1, $order->statusHistories()->count());
    }

    private function order(User $customer, string $number, OrderStatus $status = OrderStatus::Ready, string $method = 'cod'): Order
    {
        $order = $customer->orders()->create([
            'order_number' => $number, 'checkout_key' => Str::uuid()->toString(),
            'request_hash' => hash('sha256', $number), 'recipient_name' => 'Khách nhận', 'phone' => '0901234567',
            'fulfillment_type' => 'pickup', 'status' => $status, 'subtotal_vnd' => 40000,
            'discount_vnd' => 0, 'delivery_fee_vnd' => 0, 'total_vnd' => 40000,
        ]);
        $order->statusHistories()->create(['actor_id' => $customer->id, 'to_status' => $status->value]);
        $payment = $order->payment()->create(['method' => $method, 'status' => PaymentStatus::Pending, 'amount_vnd' => 40000]);
        $payment->statusHistories()->create(['to_status' => 'pending']);

        return $order;
    }
}
