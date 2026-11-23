<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\CustomerRefundRequest;
use App\Models\Order;
use App\Models\ReservationDeposit;
use App\Models\ReservationSeat;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class RefundHistoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_settled_deposit_is_visible_without_a_refund_request_and_credit_is_not_reported_as_cash_returned(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $deposit = $this->deposit($admin, 100000, 0);

        $response = $this->actingAs($admin)->get(route('operations.refunds.index'))->assertOk()
            ->assertSee($deposit->number)->assertSee('Khách hoàn cọc')->assertSee('T1-B01')
            ->assertSee('Đã khấu trừ')->assertSee('100.000')->assertSee('Hoàn lại khách');
        $entry = $response->viewData('refunds')->sole();
        $this->assertSame(0, $entry->amount_vnd);
        $this->assertSame(100000, $entry->credited_vnd);
        $this->assertSame('credited', $entry->status);
        $this->assertSame($admin->name, $entry->actor);
        $this->assertSame(0, CustomerRefundRequest::count());
        $this->assertSame('settled', $deposit->fresh()->status);
    }

    public function test_cash_surplus_refund_has_the_actual_returned_amount_and_employee_who_settled_it(): void
    {
        $staff = User::factory()->create(['name' => 'Nhân viên hoàn cọc', 'role' => UserRole::Staff]);
        $deposit = $this->deposit($staff, 40000, 60000);
        $response = $this->actingAs(User::factory()->create(['role' => UserRole::Admin]))
            ->get(route('operations.refunds.index'))->assertOk()
            ->assertSee('Hoàn tiền cọc dư tại quầy')->assertSee('60.000')->assertSee('40.000')
            ->assertSee('Tiền mặt tại quầy')->assertSee($staff->name);
        $entry = $response->viewData('refunds')->sole();
        $this->assertSame(60000, $entry->amount_vnd);
        $this->assertSame(40000, $entry->credited_vnd);
        $this->assertSame('completed', $entry->status);
        $this->assertSame(60000, $deposit->fresh()->cash_refund_vnd);
    }

    public function test_bank_refund_uses_the_original_bank_snapshot_and_does_not_duplicate_its_order_payment(): void
    {
        $owner = User::factory()->create();
        $order = $this->order($owner, 'HD0000901', 'refunded');
        $refund = CustomerRefundRequest::create([
            'user_id' => $owner->id, 'order_id' => $order->id, 'amount_vnd' => 40000,
            'bank_snapshot' => ['bank_name' => 'Ngân hàng cũ', 'account_number' => '0123456789', 'account_holder' => '<b>KHACH DEMO</b>'],
            'status' => 'completed', 'is_demo' => true, 'refund_reference' => 'DEMO-REFUND-901', 'completed_at' => now(),
        ]);
        $owner->forceFill(['linked_bank' => ['bank_name' => 'Ngân hàng mới', 'account_number' => '9988776655', 'account_holder' => 'KHACH MOI']])->save();
        $response = $this->actingAs(User::factory()->create(['role' => UserRole::Admin]))->get(route('operations.refunds.index'))
            ->assertOk()->assertSee('HD0000901')->assertSee('0123456789')->assertDontSee('9988776655')
            ->assertSee('Hệ thống (demo)')->assertSee('DEMO-REFUND-901')
            ->assertSee('<b>KHACH DEMO</b>')->assertDontSee('<b>KHACH DEMO</b>', false);
        $this->assertSame(1, $response->viewData('refunds')->total());
        $this->assertSame($refund->id, $response->viewData('refunds')->sole()->id);
        $this->assertSame('request', $response->viewData('refunds')->sole()->source);
    }

    public function test_historical_payment_refunds_and_refunded_booking_fees_are_included_with_their_audit_details(): void
    {
        $admin = User::factory()->create(['name' => 'Quản trị hoàn tiền', 'role' => UserRole::Admin]);
        $owner = User::factory()->create();
        $order = $this->order($owner, 'HD0000902', 'refunded');
        $order->payment->forceFill(['refund_reference' => 'BANK-RETURN-902', 'refunded_at' => now()])->save();
        $order->payment->statusHistories()->create(['actor_id' => $admin->id, 'from_status' => 'refund_pending', 'to_status' => 'refunded', 'reason' => 'Hoàn theo yêu cầu cửa hàng.']);
        $deposit = $this->deposit($admin, 100000, 0);
        $fee = $deposit->reservation->fees()->create(['kind' => 'reschedule', 'revision' => 0, 'amount_vnd' => 10000, 'channel' => 'app']);
        $fee->forceFill(['status' => 'refunded', 'refund_reference' => 'FEE-RETURN-902', 'refunded_at' => now(), 'refund_actor_id' => $admin->id])->save();

        $response = $this->actingAs($admin)->get(route('operations.refunds.index'))->assertOk()
            ->assertSee('BANK-RETURN-902')->assertSee('Hoàn theo yêu cầu cửa hàng.')
            ->assertSee('FEE-RETURN-902')->assertSee('Hoàn phụ phí')->assertSee('10.000')->assertSee($admin->name);
        $this->assertSame(3, $response->viewData('refunds')->total());
        $this->assertSame(0, CustomerRefundRequest::count());
    }

    public function test_pending_manual_refund_retains_validation_and_only_becomes_completed_once(): void
    {
        $owner = User::factory()->create();
        $order = $this->order($owner, 'HD0000903', 'refund_pending');
        $refund = CustomerRefundRequest::create([
            'user_id' => $owner->id, 'order_id' => $order->id, 'amount_vnd' => 40000,
            'bank_snapshot' => ['bank_name' => 'VCB', 'account_number' => '0123456789', 'account_holder' => 'KHACH'], 'status' => 'pending',
        ]);
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $this->actingAs($admin)->get(route('operations.refunds.index'))->assertOk()
            ->assertSee('Chờ hoàn tiền')->assertSee(route('operations.refunds.complete', $refund));
        $url = route('operations.refunds.complete', $refund);
        $this->postJson($url, ['refund_reference' => 'MANUAL-903'])->assertUnprocessable();
        $this->assertSame('pending', $refund->fresh()->status);
        $this->post($url, ['confirm' => 1, 'refund_reference' => 'MANUAL-903'])->assertRedirect();
        $this->post($url, ['confirm' => 1, 'refund_reference' => 'MANUAL-903'])->assertRedirect();
        $this->get(route('operations.refunds.index'))->assertOk()->assertSee('MANUAL-903')->assertSee($admin->name)
            ->assertDontSee('Ghi nhận hoàn tiền thủ công');
        $this->assertSame(1, $order->payment->statusHistories()->where('to_status', 'refunded')->count());
    }

    public function test_empty_history_pagination_and_admin_only_access(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $owner = User::factory()->create();
        $url = route('operations.refunds.index');
        $this->get($url)->assertRedirectToRoute('login');
        foreach ([$owner, User::factory()->create(['role' => UserRole::Staff])] as $actor) {
            $this->actingAs($actor)->get($url)->assertForbidden();
        }
        $this->actingAs($admin)->get($url)->assertOk()->assertSee('Chưa có lịch sử hoàn tiền hoặc khấu trừ cọc.');
        for ($i = 1; $i <= 21; $i++) {
            $order = $this->order($owner, 'HD'.str_pad((string) $i, 7, '0', STR_PAD_LEFT), 'refunded');
            $order->payment->forceFill(['refunded_at' => CarbonImmutable::now()->subMinutes($i), 'refund_reference' => 'HISTORY-'.$i])->save();
        }
        $response = $this->get($url)->assertOk()->assertSee('HISTORY-1')->assertDontSee('HISTORY-21');
        $this->assertSame(21, $response->viewData('refunds')->total());
        $this->assertCount(20, $response->viewData('refunds'));
        $this->get($url.'?page=2')->assertOk()->assertSee('HISTORY-21')->assertDontSee('HISTORY-1');
    }

    private function deposit(User $actor, int $credit, int $cash): ReservationDeposit
    {
        $owner = User::factory()->create();
        $reservation = $owner->reservations()->create([
            'reservation_seat_id' => ReservationSeat::where('code', 'T1-B01')->sole()->id,
            'code' => strtoupper(bin2hex(random_bytes(8))), 'booking_key' => (string) Str::uuid(),
            'request_hash' => hash('sha256', (string) Str::uuid()), 'contact_name' => 'Khách hoàn cọc', 'phone' => '0901234567',
            'party_size' => 2, 'fee_channel' => 'app', 'status' => 'completed',
            'scheduled_at' => now()->addHour(), 'arrival_deadline' => now()->addMinutes(90),
        ]);
        $deposit = $reservation->deposit()->create([
            'reference' => (string) Str::uuid(), 'status' => 'settled', 'amount_vnd' => $credit + $cash,
            'credited_vnd' => $credit, 'cash_refund_vnd' => $cash, 'settled_at' => now(), 'settled_by' => $actor->id,
            'bank_details' => ['bank_name' => 'Ngân hàng cửa hàng', 'account_number' => '1234567890', 'account_holder' => 'CUA HANG'],
        ]);
        $deposit->update(['number' => 'DC'.str_pad((string) $deposit->id, 7, '0', STR_PAD_LEFT)]);

        return $deposit;
    }

    private function order(User $owner, string $number, string $paymentStatus): Order
    {
        $order = $owner->orders()->create([
            'order_number' => $number, 'checkout_key' => (string) Str::uuid(), 'request_hash' => hash('sha256', $number),
            'recipient_name' => 'Khách hoàn đơn', 'phone' => '0901234567', 'fulfillment_type' => 'pickup', 'status' => 'cancelled',
            'subtotal_vnd' => 40000, 'discount_vnd' => 0, 'delivery_fee_vnd' => 0, 'total_vnd' => 40000,
        ]);
        $order->payment()->create(['method' => 'bank_transfer', 'status' => $paymentStatus, 'amount_vnd' => 40000]);

        return $order;
    }
}
