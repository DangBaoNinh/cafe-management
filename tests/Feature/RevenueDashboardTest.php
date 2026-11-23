<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Str;
use Tests\TestCase;

class RevenueDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_report_groups_paid_completed_orders_by_local_day_and_keeps_other_status_counts_separate(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $customer = User::factory()->create(['email' => 'private@example.test']);
        $this->order($customer, 'MIDNIGHT', 'completed', 'paid', '2026-11-20 17:00:00', '2026-11-20 16:00:00', 'cod');
        $this->order($customer, 'LAST-SECOND', 'completed', 'paid', '2026-11-21 16:59:59', '2026-11-20 18:00:00', 'bank_transfer');
        $this->order($customer, 'NEXT-DAY', 'completed', 'paid', '2026-11-21 17:00:00', '2026-11-21 17:00:00');
        $this->order($customer, 'BEFORE', 'completed', 'paid', '2026-11-20 16:59:59', '2026-11-20 16:00:00');
        $this->order($customer, 'AFTER', 'completed', 'paid', '2026-11-23 17:00:00', '2026-11-23 16:59:59');
        $this->order($customer, 'UNPAID', 'completed', 'pending', '2026-11-21 07:00:00', '2026-11-20 17:00:00');
        $this->order($customer, 'MISSING-PAYMENT', 'completed', null, '2026-11-21 07:00:00', '2026-11-20 17:00:00');
        $this->order($customer, 'PENDING', 'pending', 'paid', null, '2026-11-21 07:00:00');
        $this->order($customer, 'CANCELLED', 'cancelled', 'refund_pending', null, '2026-11-21 07:00:00');
        $this->order($customer, 'REJECTED', 'rejected', 'refunded', null, '2026-11-21 07:00:00');

        $response = $this->actingAs($admin)->get(route('admin.dashboard', ['from' => '2026-11-21', 'to' => '2026-11-23']))
            ->assertOk()->assertSee('21/11/2026 – 23/11/2026')->assertSee('Theo ngày hoàn tất của đơn đã thanh toán.')
            ->assertSee('Trạng thái đơn theo ngày đặt')->assertSee('Tổng số đơn đặt trong khoảng đã chọn: 8.')
            ->assertSee('126.000')->assertSee('150.000')->assertSee('30.000')->assertSee('6.000')
            ->assertDontSee('private@example.test')->assertDontSee('Khách nhận riêng')->assertDontSee('0901234567')
            ->assertDontSee('CF-MIDNIGHT')->assertDontSee('PRIVATE-REFERENCE')
            ->assertViewHas('totals', ['orders' => 3, 'subtotal' => 150000, 'discount' => 30000, 'delivery' => 6000, 'total' => 126000])
            ->assertViewHas('daily', [
                '2026-11-21' => ['orders' => 2, 'subtotal' => 100000, 'discount' => 20000, 'delivery' => 4000, 'total' => 84000],
                '2026-11-22' => ['orders' => 1, 'subtotal' => 50000, 'discount' => 10000, 'delivery' => 2000, 'total' => 42000],
                '2026-11-23' => ['orders' => 0, 'subtotal' => 0, 'discount' => 0, 'delivery' => 0, 'total' => 0],
            ])
            ->assertViewHas('statuses', [
                'pending' => 1, 'confirmed' => 0, 'preparing' => 0, 'ready' => 0, 'out_for_delivery' => 0,
                'completed' => 5, 'cancelled' => 1, 'rejected' => 1,
            ]);
        $totals = $response->viewData('totals');
        $this->assertSame($totals['subtotal'] - $totals['discount'] + $totals['delivery'], $totals['total']);
    }

    public function test_defaults_to_seven_local_calendar_days_and_shows_zeros(): void
    {
        $this->travelTo(Date::parse('2026-11-20 17:15:00', 'UTC'));
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk()
            ->assertSee('15/11/2026 – 21/11/2026')->assertSee('Tổng số đơn đặt trong khoảng đã chọn: 0.')
            ->assertViewHas('totals', ['orders' => 0, 'subtotal' => 0, 'discount' => 0, 'delivery' => 0, 'total' => 0])
            ->assertViewHas('daily', fn (array $days): bool => count($days) === 7
                && array_key_first($days) === '2026-11-15' && array_key_last($days) === '2026-11-21');
    }

    public function test_validates_dates_and_the_inclusive_maximum_range(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $url = route('admin.dashboard');
        $this->actingAs($admin);

        foreach ([
            [['from' => '2026-11-22', 'to' => '2026-11-21'], 'to'],
            [['from' => '2026-11-21'], 'to'],
            [['to' => '2026-11-21'], 'from'],
            [['from' => ['2026-11-21'], 'to' => '2026-11-21'], 'from'],
            [['from' => '2026-02-30', 'to' => '2026-03-01'], 'from'],
            [['from' => '2024-01-01', 'to' => '2025-01-01'], 'to'],
        ] as [$filters, $error]) {
            $this->getJson(route('admin.dashboard', $filters))->assertUnprocessable()->assertJsonValidationErrors($error);
        }

        $this->get(route('admin.dashboard', ['from' => '2024-01-01', 'to' => '2025-01-01']))
            ->assertRedirect($url)->assertSessionHasErrors('to');
        $this->get(route('admin.dashboard', ['from' => '2024-01-01', 'to' => '2024-12-31']))
            ->assertOk()->assertViewHas('daily', fn (array $days): bool => count($days) === 366);
    }

    public function test_only_active_admin_can_view_the_report(): void
    {
        $url = route('admin.dashboard', ['from' => '2026-11-21', 'to' => '2026-11-21']);
        $this->get($url)->assertRedirectToRoute('login');
        foreach ([UserRole::Customer, UserRole::Staff] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))->get($url)->assertForbidden();
        }
        $this->actingAs(User::factory()->create(['role' => UserRole::Admin, 'is_active' => false]))
            ->get($url)->assertForbidden();
    }

    private function order(User $customer, string $code, string $status, ?string $paymentStatus, ?string $completedAt, string $createdAt, string $method = 'cod'): void
    {
        $order = $customer->orders()->make([
            'order_number' => 'CF-'.$code, 'checkout_key' => Str::uuid()->toString(),
            'request_hash' => hash('sha256', $code), 'recipient_name' => 'Khách nhận riêng', 'phone' => '0901234567',
            'fulfillment_type' => 'delivery', 'status' => $status, 'subtotal_vnd' => 50000,
            'discount_vnd' => 10000, 'delivery_fee_vnd' => 2000, 'total_vnd' => 42000,
        ]);
        $order->forceFill(['created_at' => $createdAt, 'completed_at' => $completedAt])->save();
        if ($paymentStatus !== null) {
            $payment = $order->payment()->make(['method' => $method, 'status' => $paymentStatus, 'amount_vnd' => 42000]);
            $payment->forceFill(['paid_at' => $createdAt, 'collection_reference' => 'PRIVATE-REFERENCE-'.$code])->save();
        }
    }
}
