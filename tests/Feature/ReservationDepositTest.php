<?php

namespace Tests\Feature;

use App\Enums\ReservationStatus;
use App\Enums\UserRole;
use App\Models\Category;
use App\Models\CustomerRefundRequest;
use App\Models\Reservation;
use App\Models\ReservationDeposit;
use App\Models\ReservationSeat;
use App\Models\TableInvoice;
use App\Models\User;
use App\Services\BookingRules;
use App\Services\ReservationDeposits;
use App\Services\RevenueReport;
use App\Services\TableInvoices;
use App\Services\TableReservations;
use App\Services\TableSessions;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ReservationDepositTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-11-23 10:00:00', BookingRules::TIMEZONE));
        config(['checkout.reservation_deposit_demo' => true]);
    }

    public function test_staff_no_show_confirmation_releases_reserved_flag_and_order_warning(): void
    {
        $booking = $this->book(User::factory()->create());
        $this->pay($booking);
        $staff = User::factory()->create(['role' => UserRole::Staff]);
        $this->actingAs($staff)->postJson(route('operations.reservations.no-show', $booking->code), ['confirm' => 1])->assertConflict();
        $this->travelTo(CarbonImmutable::parse('2026-11-23 12:31:00', BookingRules::TIMEZONE));
        $this->post(route('operations.reservations.no-show', $booking->code), ['confirm' => 1])->assertRedirect();
        $this->assertSame(ReservationStatus::NoShow, $booking->fresh()->status);
        $this->assertNull($booking->fresh()->active_seat_id);
        $this->assertSame('free', $booking->seat->fresh()->availability());
        $this->get(route('operations.table-sessions.index'))->assertOk()->assertDontSee('B?n c? l?ch ??t h?m nay l?c 12:00');
    }

    private function book(User $customer, string $seat = 'T1-B01', array $overrides = []): Reservation
    {
        $this->actingAs($customer)->post(route('reservations.store'), [
            'seat_id' => ReservationSeat::where('code', $seat)->sole()->id, 'kind' => 'table', 'party_size' => 2,
            'pickup_date' => '2026-11-23', 'pickup_clock' => '12:00', 'contact_name' => 'Khách cọc', 'phone' => '0901234567',
            'channel' => 'app', 'booking_key' => (string) Str::uuid(), ...$overrides,
        ])->assertStatus(303);

        return $customer->reservations()->latest('id')->firstOrFail();
    }

    private function pay(Reservation $reservation): void
    {
        $this->post(route('reservations.deposit.pay', $reservation->code), ['confirmed' => 1, 'amount_vnd' => $reservation->deposit->amount_vnd])->assertStatus(303);
    }

    private function order(Reservation $reservation, int $total): TableInvoice
    {
        $staff = User::factory()->create(['role' => UserRole::Staff]);
        $this->actingAs($staff);
        if ($reservation->fresh()->status === ReservationStatus::Confirmed) {
            $this->post(route('operations.reservations.check-in', $reservation->code))->assertStatus(303);
        }
        $this->post(route('operations.table-sessions.table.order', $reservation->seat->code))->assertStatus(303);
        $session = $reservation->seat->activeTableSession()->firstOrFail();
        $product = Category::create(['name' => 'Cà phê', 'slug' => (string) Str::uuid()])->products()->create(['name' => 'Latte', 'slug' => (string) Str::uuid()]);
        $size = $product->sizes()->create(['code' => 'm', 'label' => 'Vừa', 'price_vnd' => $total]);
        $this->post(route('operations.table-sessions.pos.store', $session), [
            'session_reference' => $session->reference, 'submission_key' => (string) Str::uuid(), 'revision' => 0, 'method' => 'cash',
            'intent' => 'draft', 'accepted_total_vnd' => $total, 'lines' => [['product_id' => $product->id, 'size_id' => $size->id, 'quantity' => 1]],
        ])->assertStatus(303);

        return $session->invoices()->latest('id')->firstOrFail();
    }

    public function test_public_unified_page_and_demo_deposit_require_owner_amount_config_and_only_one_seat_winner(): void
    {
        $this->get(route('spaces'))->assertOk()->assertSee('Thông tin đặt bàn')->assertSee('100.000');
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $first = $this->book($owner, 'T1-B01', ['deposit_required' => false, 'method' => 'cash', 'deposit_amount_vnd' => 0]);
        $this->assertSame(ReservationStatus::PendingDeposit, $first->status);
        $this->assertNull($first->active_seat_id);
        $this->assertSame('free', $first->seat->fresh()->availability());
        $this->assertSame(100000, $first->deposit->amount_vnd);
        $second = $this->book($other);
        $this->postJson(route('reservations.deposit.pay', $first->code), ['confirmed' => 1, 'amount_vnd' => 100000])->assertNotFound();
        $this->actingAs($owner);
        $url = route('reservations.deposit.pay', $first->code);
        $this->postJson($url, ['confirmed' => 1, 'amount_vnd' => 1])->assertUnprocessable();
        config(['checkout.reservation_deposit_demo' => false]);
        $this->postJson($url, ['confirmed' => 1, 'amount_vnd' => 100000])->assertForbidden();
        config(['checkout.reservation_deposit_demo' => true]);
        $this->pay($first);
        $this->pay($first);
        $this->assertSame('paid', $first->deposit->fresh()->status);
        $this->assertSame('held', $first->seat->fresh()->availability());
        $this->assertSame(2, $first->events()->count());
        $this->actingAs($other)->postJson(route('reservations.deposit.pay', $second->code), ['confirmed' => 1, 'amount_vnd' => 100000])->assertConflict();
        $this->assertSame('pending', $second->deposit->fresh()->status);
        $this->actingAs(User::factory()->create(['role' => UserRole::Admin]))->get(route('admin.deposits.index'))
            ->assertOk()->assertSee($first->deposit->number)->assertSee('Quản lý tiền cọc');
        $this->get(route('operations.deposits.show', $first->deposit))->assertOk()->assertSee('Chứng từ đặt cọc');
    }

    public function test_credit_is_automatic_once_and_deposit_settles_only_on_invoice_payment(): void
    {
        $reservation = $this->book(User::factory()->create());
        $this->pay($reservation);
        $invoice = $this->order($reservation, 150000);
        $this->assertSame(100000, $invoice->deposit_credit_vnd);
        $this->assertSame(50000, $invoice->amount_due_vnd);
        $this->assertSame('paid', $reservation->deposit->fresh()->status);
        $this->assertSame(ReservationStatus::CheckedIn, $reservation->fresh()->status);
        $this->get(route('operations.invoices.prepaid.show', $invoice))->assertOk()->assertSee('Trừ tiền cọc')->assertSee('50.000');
        $this->post(route('operations.invoices.prepaid.pay', $invoice), ['revision' => 1])->assertStatus(303);
        $this->post(route('operations.invoices.prepaid.pay', $invoice), ['revision' => 1])->assertStatus(303);
        $deposit = $reservation->deposit->fresh();
        $this->assertSame('settled', $deposit->status);
        $this->assertSame(100000, $deposit->credited_vnd);
        $this->assertSame(0, $deposit->cash_refund_vnd);
        $second = $this->order($reservation, 40000);
        $this->assertSame(0, $second->deposit_credit_vnd);
        $this->assertSame(40000, $second->amount_due_vnd);
        $this->actingAs(User::factory()->create(['role' => UserRole::Admin]));
        $report = $this->get(route('admin.dashboard', ['from' => '2026-11-23', 'to' => '2026-11-23']))->assertOk();
        $this->assertSame(150000, $report->viewData('totals')['total']);
        $this->assertSame(1, $report->viewData('totals')['orders']);
        $this->assertSame(100000, $report->viewData('channels')['bank_transfer']['total']);
        $this->assertSame(50000, $report->viewData('channels')['cash']['total']);
    }

    public function test_small_invoice_requires_cash_refund_confirmation_and_cancelled_draft_releases_credit(): void
    {
        $reservation = $this->book(User::factory()->create());
        $this->pay($reservation);
        $invoice = $this->order($reservation, 40000);
        $this->assertSame(60000, $invoice->deposit_refund_vnd);
        $this->assertSame(0, $invoice->amount_due_vnd);
        $pay = route('operations.invoices.prepaid.pay', $invoice);
        $this->postJson($pay, ['revision' => 1])->assertConflict();
        $this->assertSame('draft', $invoice->fresh()->status);
        $this->assertSame('paid', $reservation->deposit->fresh()->status);
        $this->post(route('operations.table-sessions.advance', [$invoice->tableSession, $invoice->table_round_id]), ['expected' => 'pending', 'target' => 'cancelled', 'reason' => 'Đổi phiếu'])->assertStatus(303);
        $this->assertNull($reservation->deposit->fresh()->table_invoice_id);
        $replacement = $this->order($reservation, 60000);
        $this->post(route('operations.invoices.prepaid.pay', $replacement), ['revision' => 1, 'deposit_refund_confirmed' => 1])->assertStatus(303);
        $this->assertSame(60000, $reservation->deposit->fresh()->credited_vnd);
        $this->assertSame(40000, $reservation->deposit->fresh()->cash_refund_vnd);
        $this->assertSame('settled', $reservation->deposit->fresh()->status);
    }

    public function test_customer_cancellation_requests_refund_while_no_show_forfeits(): void
    {
        config(['checkout.customer_refund_demo' => false]);
        $first = $this->book(User::factory()->create());
        $this->pay($first);
        $this->postJson(route('reservations.cancel', $first->code), ['confirm' => 1])->assertUnprocessable();
        auth()->user()->forceFill(['linked_bank' => ['bank_name' => 'VCB', 'account_number' => '0123456789', 'account_holder' => 'KHACH']])->save();
        $this->post(route('reservations.cancel', $first->code), ['confirm' => 1])->assertStatus(303);
        $this->assertSame('refund_pending', $first->deposit->fresh()->status);
        $this->assertDatabaseHas('customer_refund_requests', ['reservation_deposit_id' => $first->deposit->id, 'amount_vnd' => 100000]);
        $second = $this->book(User::factory()->create(), 'T1-B02');
        $this->pay($second);
        $this->travelTo(CarbonImmutable::parse('2026-11-23 12:30:00', BookingRules::TIMEZONE));
        $this->assertTrue(app(TableReservations::class)->expire($second->id, now()));
        $this->assertFalse(app(TableReservations::class)->expire($second->id, now()));
        $this->assertSame('forfeited', $second->deposit->fresh()->status);
        $this->assertSame(1, ReservationDeposit::where('status', 'forfeited')->count());
        $from = CarbonImmutable::parse('2026-11-23', BookingRules::TIMEZONE);
        $report = app(RevenueReport::class)->summarize($from, $from);
        $this->assertSame(100000, $report['totals']['total']);
        $this->assertSame(100000, $report['channels']['bank_transfer']['total']);
    }

    public function test_room_requires_300k_and_calendar_reserves_only_the_selected_day(): void
    {
        $owner = User::factory()->create();
        $room = $this->book($owner, 'T1-PH', ['kind' => 'room', 'party_size' => 10, 'pickup_date' => '2026-11-24']);
        $this->assertSame(300000, $room->deposit->amount_vnd);
        $this->postJson(route('reservations.deposit.pay', $room->code), ['confirmed' => 1, 'amount_vnd' => 100000])->assertUnprocessable();
        $this->pay($room);
        $this->assertNull($room->fresh()->active_seat_id);
        $id = (string) $room->reservation_seat_id;
        $today = collect($this->getJson(route('spaces.snapshot', ['date' => '2026-11-23']))->assertOk()->json('tables'))->keyBy('id');
        $tomorrow = collect($this->getJson(route('spaces.snapshot', ['date' => '2026-11-24']))->assertOk()->json('tables'))->keyBy('id');
        $this->assertSame('available', $today[$id]['status']);
        $this->assertSame('reserved', $tomorrow[$id]['status']);
        $this->assertStringContainsString('12:00', $tomorrow[$id]['scheduled_at']);
        $otherDay = $this->book($owner, 'T1-PH', ['kind' => 'room', 'party_size' => 10, 'pickup_date' => '2026-11-25']);
        $this->pay($otherDay);
        $this->postJson(route('reservations.store'), [
            'seat_id' => $room->reservation_seat_id, 'kind' => 'room', 'party_size' => 10,
            'pickup_date' => '2026-11-24', 'pickup_clock' => '18:00', 'contact_name' => 'Khách', 'phone' => '0901234567',
            'channel' => 'app', 'booking_key' => (string) Str::uuid(),
        ])->assertUnprocessable();
    }

    public function test_customer_cancel_after_30_minutes_is_rejected_and_admin_can_finish_refund_once(): void
    {
        config(['checkout.customer_refund_demo' => false]);
        $owner = User::factory()->create();
        $owner->forceFill(['linked_bank' => ['bank_name' => 'VCB', 'account_number' => '0123456789', 'account_holder' => 'KHACH']])->save();
        $reservation = $this->book($owner);
        $this->pay($reservation);
        $this->travel(31)->minutes();
        $this->postJson(route('reservations.cancel', $reservation->code), ['confirm' => 1])->assertUnprocessable();
        $this->assertSame('paid', $reservation->deposit->fresh()->status);
        $this->travelBack();
        $this->travelTo(CarbonImmutable::parse('2026-11-23 10:20:00', BookingRules::TIMEZONE));
        $this->post(route('reservations.cancel', $reservation->code), ['confirm' => 1])->assertStatus(303);
        $refund = CustomerRefundRequest::sole();
        $this->post(route('operations.refunds.complete', $refund), ['confirm' => 1, 'refund_reference' => 'REFUND-001'])->assertForbidden();
        $this->actingAs(User::factory()->create(['role' => UserRole::Staff]));
        $this->get(route('operations.refunds.index'))->assertForbidden();
        $this->actingAs(User::factory()->create(['role' => UserRole::Admin]));
        $this->get(route('operations.refunds.index'))->assertOk()->assertSee('0123456789');
        $this->postJson(route('operations.refunds.complete', $refund), [])->assertUnprocessable();
        $this->post(route('operations.refunds.complete', $refund), ['confirm' => 1, 'refund_reference' => 'REFUND-001'])->assertRedirect();
        $this->post(route('operations.refunds.complete', $refund), ['confirm' => 1, 'refund_reference' => 'REFUND-001'])->assertRedirect();
        $this->assertSame('refunded', $reservation->deposit->fresh()->status);
        $this->assertSame('completed', $refund->fresh()->status);
    }

    public function test_only_admin_can_reschedule_future_bookings_without_losing_the_deposit(): void
    {
        $owner = User::factory()->create();
        $booking = $this->book($owner, 'T1-B01', ['pickup_date' => '2026-11-24']);
        $this->pay($booking);
        $other = $this->book($owner, 'T1-B01', ['pickup_date' => '2026-11-25']);
        $this->pay($other);
        $url = route('operations.reservations.reschedule', $booking->code);
        $data = ['pickup_date' => '2026-11-26', 'pickup_clock' => '13:00', 'revision' => 0, 'confirm' => 1];
        $this->postJson($url, $data)->assertForbidden();
        $this->actingAs(User::factory()->create(['role' => UserRole::Staff]));
        $this->get(route('operations.reservations.show', $booking->code))->assertOk()->assertDontSee('Điều chỉnh ngày / giờ');
        $this->postJson($url, $data)->assertForbidden();
        $this->actingAs(User::factory()->create(['role' => UserRole::Admin]));
        $this->get(route('operations.reservations.show', $booking->code))->assertOk()->assertSee('Điều chỉnh ngày / giờ');
        $this->postJson($url, [...$data, 'confirm' => 0])->assertUnprocessable();
        $this->postJson($url, [...$data, 'pickup_date' => '2026-11-25'])->assertUnprocessable();
        $this->post($url, $data)->assertStatus(303);
        $this->postJson($url, $data)->assertUnprocessable();
        $this->assertSame('2026-11-26', $booking->fresh()->scheduled_at->setTimezone(BookingRules::TIMEZONE)->toDateString());
        $this->assertSame('paid', $booking->deposit->fresh()->status);
        $this->assertNull($booking->deposit->fresh()->table_invoice_id);
    }

    public function test_room_deposit_is_applied_as_300k_to_the_invoice(): void
    {
        $booking = $this->book(User::factory()->create(), 'T1-PH', ['kind' => 'room', 'party_size' => 10]);
        $this->pay($booking);
        $invoice = $this->order($booking, 350000);
        $this->assertSame(300000, $invoice->deposit_credit_vnd);
        $this->assertSame(50000, $invoice->amount_due_vnd);
        $this->post(route('operations.invoices.prepaid.pay', $invoice), ['revision' => 1])->assertStatus(303);
        $this->assertSame('settled', $booking->deposit->fresh()->status);
        $this->assertSame(300000, $booking->deposit->fresh()->credited_vnd);
    }

    public function test_pending_request_can_be_left_and_paid_later_without_cancelling_its_deposit(): void
    {
        $booking = $this->book(User::factory()->create());
        $events = $booking->events()->count();
        $this->get(route('reservations.show', $booking->code))->assertOk()->assertSee('Quay lại không gian')->assertDontSee('Hủy yêu cầu chưa cọc');
        $this->post(route('reservations.cancel', $booking->code), ['confirm' => 1])->assertStatus(303);
        $this->assertSame(ReservationStatus::PendingDeposit, $booking->fresh()->status);
        $this->assertSame('pending', $booking->deposit->fresh()->status);
        $this->assertSame($events, $booking->events()->count());
        $this->pay($booking);
        $this->assertSame('paid', $booking->deposit->fresh()->status);
    }

    public function test_customer_cancel_at_30_minutes_refunds_demo_once_and_after_deadline_button_is_disabled(): void
    {
        $owner = User::factory()->create();
        $owner->forceFill(['linked_bank' => ['bank_name' => 'VCB', 'account_number' => '0123456789', 'account_holder' => 'KHACH']])->save();
        $booking = $this->book($owner);
        $this->pay($booking);
        $later = $this->book($owner, 'T1-B02');
        $this->pay($later);
        $this->travel(30)->minutes();
        $this->assertTrue($booking->fresh()->canCustomerCancel());
        $this->post(route('reservations.cancel', $booking->code), ['confirm' => 1])->assertStatus(303);
        $this->assertSame('refunded', $booking->deposit->fresh()->status);
        $refund = CustomerRefundRequest::sole();
        $this->assertSame('completed', $refund->status);
        $this->assertTrue((bool) $refund->is_demo);
        $this->assertSame('0123456789', $refund->bank_snapshot['account_number']);
        $this->postJson(route('reservations.cancel', $booking->code), ['confirm' => 1])->assertUnprocessable();
        $this->assertSame(1, CustomerRefundRequest::count());
        $this->travel(1)->seconds();
        $this->assertFalse($later->fresh()->canCustomerCancel());
        $html = $this->get(route('reservations.show', $later->code))->assertOk()->getContent();
        $dom = new \DOMDocument;
        @$dom->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'));
        $button = (new \DOMXPath($dom))->query('//button[@data-cancel-until]')->item(0);
        $this->assertNotNull($button);
        $this->assertTrue($button->hasAttribute('disabled'));
        $this->postJson(route('reservations.cancel', $later->code), ['confirm' => 1])->assertUnprocessable();
        $this->assertSame('paid', $later->deposit->fresh()->status);
    }

    public function test_regular_order_does_not_check_in_or_claim_deposit_and_explicit_check_in_is_same_day_only(): void
    {
        $owner = User::factory()->create();
        $booking = $this->book($owner);
        $this->pay($booking);
        $tomorrow = $this->book($owner, 'T1-B02', ['pickup_date' => '2026-11-24']);
        $this->pay($tomorrow);
        $staff = User::factory()->create(['role' => UserRole::Staff]);
        $this->actingAs($staff)->postJson(route('operations.reservations.check-in', $tomorrow->code))->assertUnprocessable();
        $this->get(route('operations.reservations.show', $tomorrow->code))->assertOk()->assertDontSee('Điều chỉnh ngày / giờ')->assertDontSee('Hủy lịch và trả chỗ');
        $this->postJson(route('operations.reservations.cancel', $booking->code), ['reason' => 'test'])->assertForbidden();
        $this->postJson(route('operations.reservations.reschedule', $booking->code), [])->assertForbidden();
        $this->get(route('operations.table-sessions.index'))->assertOk()->assertSee('Bàn có lịch đặt hôm nay lúc 12:00');
        $this->post(route('operations.table-sessions.table.order', $booking->seat->code))->assertStatus(303);
        $session = $booking->seat->activeTableSession()->sole();
        $this->assertNull($session->reservation_id);
        $this->assertSame(ReservationStatus::Confirmed, $booking->fresh()->status);
        $this->assertSame(0, app(ReservationDeposits::class)->available($session, null));
        $this->get(route('operations.table-sessions.pos', $session))->assertOk()->assertSee('Order tại đây tính bình thường');
        $product = Category::create(['name' => 'Walk-in', 'slug' => (string) Str::uuid()])->products()->create(['name' => 'Cafe', 'slug' => (string) Str::uuid()]);
        $size = $product->sizes()->create(['code' => 'm', 'label' => 'Vừa', 'price_vnd' => 30000]);
        $this->post(route('operations.table-sessions.pos.store', $session), [
            'session_reference' => $session->reference, 'submission_key' => (string) Str::uuid(), 'revision' => 0, 'method' => 'cash',
            'intent' => 'draft', 'accepted_total_vnd' => 30000, 'lines' => [['product_id' => $product->id, 'size_id' => $size->id, 'quantity' => 1]],
        ])->assertStatus(303);
        $this->assertSame(0, $session->invoices()->sole()->deposit_credit_vnd);
        $this->postJson(route('operations.reservations.check-in', $booking->code))->assertUnprocessable();
        app(TableSessions::class)->close($staff, $session);
        $this->post(route('operations.reservations.check-in', $booking->code))->assertStatus(303);
        $invoice = $this->order($booking, 150000);
        $this->assertSame(100000, $invoice->deposit_credit_vnd);
    }

    public function test_admin_manages_deposits_separately_and_staff_cannot_see_financial_documents(): void
    {
        $booking = $this->book(User::factory()->create());
        $this->pay($booking);
        $staff = User::factory()->create(['role' => UserRole::Staff]);
        $this->actingAs($staff);
        $this->get(route('operations.deposits.show', $booking->deposit))->assertForbidden();
        $this->get(route('operations.refunds.index'))->assertForbidden();
        $this->get(route('admin.deposits.index'))->assertForbidden();
        $this->get(route('operations.invoices.history'))->assertOk()->assertDontSee($booking->deposit->number)->assertDontSee('Lịch nhận tại quán');
        $this->actingAs(User::factory()->create(['role' => UserRole::Admin]));
        $this->get(route('admin.invoices.index'))->assertOk()->assertDontSee($booking->deposit->number);
        $this->get(route('admin.deposits.index'))->assertOk()->assertSee($booking->deposit->number)->assertSee('Quản lý phiếu đặt bàn')->assertDontSee('Quản lý đơn hàng');
        $url = route('admin.deposits.forfeit', $booking->deposit);
        $this->postJson($url, ['confirm' => 1])->assertUnprocessable();
        $this->post($url, ['reason' => 'Hủy theo thỏa thuận giữ cọc', 'confirm' => 1])->assertRedirect();
        $this->assertSame('forfeited', $booking->deposit->fresh()->status);
        $this->assertSame('Hủy theo thỏa thuận giữ cọc', $booking->deposit->fresh()->forfeit_reason);
        $this->get(route('admin.deposits.index', ['status' => 'forfeited']))->assertOk()->assertSee('Hủy theo thỏa thuận giữ cọc');
    }

    public function test_admin_confirmation_requires_acknowledging_the_cash_refund(): void
    {
        $reservation = $this->book(User::factory()->create());
        $this->pay($reservation);
        $invoice = $this->order($reservation, 40000);
        $this->actingAs(User::factory()->create(['role' => UserRole::Admin]));
        $preview = route('admin.counter-orders.preview', $invoice);
        $this->postJson($preview, ['target' => 'completed', 'revision' => 1])->assertUnprocessable()->assertJsonValidationErrors('deposit_refund_confirmed');
        $confirmation = $this->post($preview, ['target' => 'completed', 'revision' => 1, 'deposit_refund_confirmed' => 1])->assertOk();
        $this->assertSame('paid', $reservation->deposit->fresh()->status);
        $this->post(route('admin.counter-orders.update', $invoice), ['confirmation_token' => $confirmation->viewData('token')])->assertStatus(303);
        $this->assertSame('settled', $reservation->deposit->fresh()->status);
        $this->assertSame(60000, $reservation->deposit->fresh()->cash_refund_vnd);
    }

    public function test_invoice_payment_failure_rolls_back_deposit_application(): void
    {
        $reservation = $this->book(User::factory()->create());
        $this->pay($reservation);
        $invoice = $this->order($reservation, 40000);
        $actor = User::findOrFail(auth()->id());
        TableInvoice::saving(function (TableInvoice $saving): void {
            if ($saving->status === 'paid') {
                throw new \RuntimeException('Payment write failed');
            }
        });
        try {
            app(TableInvoices::class)->completeSaved($actor, $invoice, 1, true);
            $this->fail('The invoice update must fail.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Payment write failed', $exception->getMessage());
        } finally {
            TableInvoice::flushEventListeners();
        }
        $this->assertSame('draft', $invoice->fresh()->status);
        $deposit = $reservation->deposit->fresh();
        $this->assertSame('paid', $deposit->status);
        $this->assertSame(0, $deposit->cash_refund_vnd);
        $this->assertSame(0, $deposit->credited_vnd);
    }
}
