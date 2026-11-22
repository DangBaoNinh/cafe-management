<?php

namespace Tests\Feature;

use App\Enums\CollectionMethod;
use App\Enums\UserRole;
use App\Models\Category;
use App\Models\ReservationSeat;
use App\Models\TableInvoice;
use App\Models\TableInvoiceRevision;
use App\Models\User;
use App\Services\PaidCounterOrderStatus;
use App\Services\TableInvoices;
use App\Services\TableSessions;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminCounterWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private function invoice(string $method = 'cash', bool $paid = false): TableInvoice
    {
        $staff = User::factory()->create(['role' => UserRole::Staff]);
        $seat = ReservationSeat::where('code', 'T1-B01')->sole();
        $session = $seat->activeTableSession()->first() ?? app(TableSessions::class)->open($staff, $seat);
        $category = Category::create(['name' => 'Cà phê', 'slug' => (string) Str::uuid()]);
        $product = $category->products()->create(['name' => 'Latte', 'slug' => (string) Str::uuid()]);
        $size = $product->sizes()->create(['code' => 'm', 'label' => 'Vừa', 'price_vnd' => 40000]);

        return app(TableInvoices::class)->save($session, [['product_id' => $product->id, 'size_id' => $size->id, 'quantity' => 1, 'sugar_level' => null, 'ice_level' => null, 'topping_ids' => []]],
            (string) Str::uuid(), null, 0, CollectionMethod::from($method), 40000, false, $paid, $staff);
    }

    private function confirmation(TableInvoice $invoice, array $input): array
    {
        $response = $this->post(route('admin.counter-orders.preview', $invoice), $input)->assertOk()
            ->assertSee('Xác minh cập nhật trạng thái')->assertSee('Đơn hàng chưa được thay đổi.');

        return ['confirmation_token' => $response->viewData('token')];
    }

    public function test_admin_changes_paid_order_status_after_confirmation_without_changing_money_or_stock(): void
    {
        $invoice = $this->invoice('cash', true);
        $money = $invoice->only(['number', 'status', 'total_vnd', 'subtotal_vnd', 'fees_vnd', 'method', 'paid_at', 'paid_by', 'selections', 'initial_items']);
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $this->actingAs($admin);
        $url = route('admin.counter-orders.update', $invoice);
        $this->get(route('admin.invoices.index'))->assertOk()->assertSee('Thay đổi trạng thái');
        $this->get(route('admin.invoices.show', $invoice->number))->assertOk()->assertSee('Tiếp tục xác minh');
        $this->postJson(route('admin.counter-orders.preview', $invoice), ['target' => 'cancelled', 'revision' => 1])->assertUnprocessable();
        $day = now('Asia/Ho_Chi_Minh')->toDateString();
        $before = $this->get(route('admin.dashboard', ['from' => $day, 'to' => $day]))->assertOk()->viewData('totals');
        $cancel = $this->confirmation($invoice, ['target' => 'cancelled', 'revision' => 1, 'reason' => 'Điều chỉnh nghiệp vụ']);
        $this->assertSame('completed', $invoice->refresh()->order_status->value);
        $this->post($url, $cancel)->assertStatus(303);
        $this->assertSame('cancelled', $invoice->refresh()->order_status->value);
        $this->assertEquals($money, $invoice->only(array_keys($money)));
        $this->assertSame(2, $invoice->revision);
        $this->assertSame('completed', $invoice->round->status->value);
        $this->assertSame(1, $invoice->round->events()->count());
        $audit = $invoice->revisions()->sole();
        $this->assertSame($admin->id, $audit->actor_id);
        $this->assertSame('completed', $audit->before['order_status']);
        $this->assertSame('cancelled', $audit->after['order_status']);
        $this->assertSame('Điều chỉnh nghiệp vụ', $audit->after['reason']);
        $this->postJson($url, $cancel)->assertConflict();
        $this->assertSame(1, $invoice->revisions()->count());
        $after = $this->get(route('admin.dashboard', ['from' => $day, 'to' => $day]))->assertOk()->viewData('totals');
        $this->assertSame(40000, $before['total']);
        $this->assertSame(0, $after['total']);
        $this->assertSame(0, $after['orders']);
        $this->get(route('admin.invoices.index', ['display_status' => 'cancelled']))->assertOk()->assertSee($invoice->number);
        $this->get(route('admin.invoices.show', $invoice->number))->assertOk()->assertSee('Điều chỉnh nghiệp vụ')->assertSee('Hóa đơn thanh toán');
        $restore = $this->confirmation($invoice, ['target' => 'completed', 'revision' => 2, 'reason' => 'Xác minh lại đơn']);
        $this->post($url, $restore)->assertStatus(303);
        $this->assertSame('completed', $invoice->refresh()->order_status->value);
        $this->assertEquals($money, $invoice->only(array_keys($money)));
        $this->assertSame(2, $invoice->revisions()->count());
        $this->get(route('admin.invoices.show', $invoice->number))->assertOk()->assertSee('Xác minh lại đơn');
        $this->assertSame($before, $this->get(route('admin.dashboard', ['from' => $day, 'to' => $day]))->assertOk()->viewData('totals'));
    }

    public function test_cancelled_paid_invoices_are_removed_from_daily_totals_fees_and_each_payment_channel(): void
    {
        $cash = $this->invoice('cash', true);
        $bank = $this->invoice('bank_transfer', true);
        $bank->update(['fees_vnd' => 5000, 'total_vnd' => 45000]);
        $this->actingAs(User::factory()->create(['role' => UserRole::Admin]));
        $day = now('Asia/Ho_Chi_Minh')->toDateString();
        $report = fn () => $this->get(route('admin.dashboard', ['from' => $day, 'to' => $day]))->assertOk();
        $initial = $report();
        $this->assertSame(85000, $initial->viewData('totals')['total']);
        $this->assertSame(5000, $initial->viewData('feesTotal'));
        $cancel = $this->confirmation($cash, ['target' => 'cancelled', 'revision' => 1, 'reason' => 'Hủy hóa đơn tiền mặt']);
        $this->post(route('admin.counter-orders.update', $cash), $cancel)->assertStatus(303);
        $afterCash = $report();
        $this->assertSame(45000, $afterCash->viewData('totals')['total']);
        $this->assertSame(['orders' => 0, 'total' => 0], $afterCash->viewData('channels')['cash']);
        $this->assertSame(45000, $afterCash->viewData('channels')['bank_transfer']['total']);
        $this->assertSame(45000, $afterCash->viewData('daily')[$day]['total']);
        $this->postJson(route('admin.counter-orders.update', $cash), $cancel)->assertConflict();
        $cancelBank = $this->confirmation($bank, ['target' => 'cancelled', 'revision' => 1, 'reason' => 'Hủy hóa đơn chuyển khoản']);
        $this->post(route('admin.counter-orders.update', $bank), $cancelBank)->assertStatus(303);
        $empty = $report();
        $this->assertSame(0, $empty->viewData('totals')['total']);
        $this->assertSame(0, $empty->viewData('feesTotal'));
        $this->assertSame(0, $empty->viewData('dailyFees')[$day]);
        $this->assertSame(0, array_sum($empty->viewData('dailyChannels')[$day]));
        $this->assertSame(['orders' => 0, 'total' => 0], $empty->viewData('channels')['bank_transfer']);
        $this->assertSame('paid', $cash->refresh()->status);
        $this->assertSame('paid', $bank->refresh()->status);
        $restore = $this->confirmation($bank, ['target' => 'completed', 'revision' => 2, 'reason' => 'Khôi phục hóa đơn']);
        $this->post(route('admin.counter-orders.update', $bank), $restore)->assertStatus(303);
        $restored = $report();
        $this->assertSame(45000, $restored->viewData('totals')['total']);
        $this->assertSame(5000, $restored->viewData('feesTotal'));
        $this->assertSame(45000, $restored->viewData('channels')['bank_transfer']['total']);
    }

    public function test_paid_status_audit_failure_rolls_back_and_staff_cannot_use_the_service(): void
    {
        $invoice = $this->invoice('cash', true);
        $staff = User::factory()->create(['role' => UserRole::Staff]);
        try {
            app(PaidCounterOrderStatus::class)->change($staff, $invoice, 'cancelled', 1, 'Không đủ quyền');
            $this->fail('Staff must not change paid order status.');
        } catch (AuthorizationException) {
            $this->assertSame('completed', $invoice->refresh()->order_status->value);
        }
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        TableInvoiceRevision::creating(function (): void {
            throw new \RuntimeException('Status audit failed');
        });
        try {
            app(PaidCounterOrderStatus::class)->change($admin, $invoice, 'cancelled', 1, 'Lỗi lịch sử');
            $this->fail('Audit insertion must fail.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Status audit failed', $exception->getMessage());
        } finally {
            TableInvoiceRevision::flushEventListeners();
        }
        $this->assertSame('completed', $invoice->refresh()->order_status->value);
        $this->assertSame('paid', $invoice->status);
        $this->assertSame(1, $invoice->revision);
        $this->assertSame(0, $invoice->revisions()->count());
    }

    public function test_invoice_management_exposes_status_action_and_confirmation_returns_to_invoice_workspace(): void
    {
        $invoice = $this->invoice();
        $index = route('admin.invoices.index');
        $detail = route('admin.invoices.show', $invoice->number);
        $this->get($index)->assertRedirectToRoute('login');
        $this->actingAs(User::factory()->create(['role' => UserRole::Staff]))->get($index)->assertForbidden();
        $this->actingAs(User::factory()->create(['role' => UserRole::Admin]));
        $this->get(route('admin.dashboard'))->assertOk()->assertSee($index)->assertSee('Quản lý hóa đơn');
        $this->get($index)->assertOk()->assertSee('Thay đổi trạng thái')->assertSee($detail);
        $this->get(route('operations.invoices.index'))->assertOk()->assertSee($detail)->assertSee('Thay đổi trạng thái');
        $this->get($detail)->assertOk()->assertSee('Thay đổi trạng thái hóa đơn')->assertSee('Tiếp tục xác minh');
        $preview = $this->post(route('admin.counter-orders.preview', $invoice), ['target' => 'completed', 'revision' => 1, 'return_to_invoices' => 1])
            ->assertOk()->assertSee($detail);
        $this->assertSame('draft', $invoice->refresh()->status);
        $this->post(route('admin.counter-orders.update', $invoice), ['confirmation_token' => $preview->viewData('token'), 'return_to_invoices' => 1])
            ->assertRedirect($detail);
        $this->assertSame('paid', $invoice->refresh()->status);
        $this->get($detail)->assertOk()->assertSee('Hóa đơn đã thu được giữ nguyên');
        $this->get(route('operations.invoices.history'))->assertOk()->assertSee($detail)->assertSee('Thay đổi trạng thái');
        $this->get($index)->assertOk()->assertSee('Đã thanh toán');
        $this->get(route('admin.invoices.index', ['display_status' => 'pending']))->assertOk()
            ->assertViewHas('orders', fn ($orders) => $orders->total() === 0);
        $this->post(route('admin.counter-orders.update', $invoice), ['confirmation_token' => 'invalid', 'return_to_invoices' => 1])
            ->assertRedirect($detail)->assertSessionHasErrors('confirmation_token');
    }

    public function test_admin_reaches_status_editor_from_operations_invoice_links_while_staff_keeps_receipt(): void
    {
        $invoice = $this->invoice();
        $staff = User::factory()->create(['role' => UserRole::Staff]);
        $receipt = route('operations.invoices.prepaid.show', $invoice);
        $editor = route('admin.orders.show', $invoice->number);
        $this->actingAs($staff)->get($receipt)->assertOk()->assertSee('Hoàn tất thanh toán')->assertSee('Quay về nhận order');
        $this->actingAs(User::factory()->create(['role' => UserRole::Admin]));
        $this->get($receipt)->assertRedirect($editor);
        $this->get(route('operations.invoices.index'))->assertOk()->assertSee($editor)->assertDontSee($receipt);
        $this->get($editor)->assertOk()->assertSee('Tiếp tục xác minh')->assertDontSee('Quay về nhận order');
        $confirmed = $this->confirmation($invoice, ['target' => 'completed', 'revision' => 1]);
        $this->post(route('admin.counter-orders.update', $invoice), $confirmed)->assertRedirect($editor);
        $this->assertSame('paid', $invoice->refresh()->status);
        $this->get($receipt)->assertRedirect($editor);
        $this->get(route('operations.invoices.history'))->assertOk()->assertSee($editor)->assertDontSee($receipt);
        $this->get($editor)->assertOk()->assertSee('Hóa đơn thanh toán')->assertDontSee('Quay về nhận order');
    }

    public function test_confirmation_is_required_signed_actor_bound_and_expires(): void
    {
        $invoice = $this->invoice();
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $url = route('admin.counter-orders.update', $invoice);
        $this->actingAs($admin)->postJson($url, ['target' => 'completed', 'revision' => 1])->assertUnprocessable()->assertJsonValidationErrors('confirmation_token');
        $confirmed = $this->confirmation($invoice, ['target' => 'completed', 'revision' => 1]);
        $this->assertSame('draft', $invoice->refresh()->status);
        $this->postJson($url, ['confirmation_token' => 'invalid-token'])->assertUnprocessable();
        $other = $this->invoice();
        $this->postJson(route('admin.counter-orders.update', $other), $confirmed)->assertUnprocessable();
        $this->actingAs(User::factory()->create(['role' => UserRole::Admin]))->postJson($url, $confirmed)->assertUnprocessable();
        $this->actingAs($admin);
        $longReason = str_repeat('đ', 500);
        $cancel = $this->confirmation($other, ['target' => 'cancelled', 'revision' => 1, 'reason' => $longReason]);
        $this->post(route('admin.counter-orders.update', $other), [...$cancel, 'target' => 'completed'])->assertStatus(303);
        $this->assertSame('voided', $other->refresh()->status);
        $this->assertSame($longReason, $other->round->events()->sole()->reason);
        $this->travel(11)->minutes();
        $this->postJson($url, $confirmed)->assertUnprocessable();
        $this->from(route('admin.counter-orders.preview', $invoice))->post($url, $confirmed)
            ->assertRedirectToRoute('admin.orders.show', $invoice->number)->assertSessionHasErrors('confirmation_token');
        $this->assertSame('draft', $invoice->refresh()->status);
    }

    public function test_staff_drafts_appear_in_admin_with_filters_and_detail(): void
    {
        $invoice = $this->invoice();
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $filters = ['source' => 'counter', 'display_status' => 'pending', 'method' => 'cash', 'number' => $invoice->number];
        $this->actingAs($admin)->get(route('admin.orders.index', $filters))->assertOk()
            ->assertSee($invoice->number)->assertSee('Chờ thanh toán')->assertSee('order-state--pending', false)
            ->assertViewHas('orders', fn ($orders) => $orders->total() === 1);
        $this->get(route('admin.orders.index', [...$filters, 'display_status' => 'paid']))->assertOk()
            ->assertViewHas('orders', fn ($orders) => $orders->total() === 0);
        $this->get(route('admin.orders.show', $invoice->number))->assertOk()->assertSee('Cập nhật trạng thái đơn hàng')->assertSee('Tiếp tục xác minh')->assertSee('Latte')->assertDontSee('Quay về nhận order')->assertDontSee(route('operations.invoices.prepaid.pay', $invoice));
        $this->getJson(route('admin.orders.index', ['source' => 'bad', 'display_status' => 'bad']))->assertUnprocessable();
    }

    public function test_admin_settles_saved_snapshot_once_and_cannot_cancel_paid_invoice(): void
    {
        $invoice = $this->invoice();
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $url = route('admin.counter-orders.update', $invoice);
        $this->actingAs($admin);
        $confirmed = $this->confirmation($invoice, ['target' => 'completed', 'revision' => 1]);
        $this->assertSame('draft', $invoice->refresh()->status);
        $this->post($url, $confirmed)->assertRedirectToRoute('admin.orders.show', $invoice->number);
        $this->post($url, $confirmed)->assertStatus(303);
        $this->assertSame('paid', $invoice->refresh()->status);
        $this->assertSame($admin->id, $invoice->paid_by);
        $this->assertSame(1, $invoice->round->events()->count());
        $this->postJson(route('admin.counter-orders.preview', $invoice), ['target' => 'cancelled', 'revision' => 1, 'reason' => 'Hủy'])->assertOk();
        $this->assertSame('paid', $invoice->refresh()->status);
        $this->get(route('operations.invoices.history'))->assertOk()->assertSee($invoice->number);
        $this->get(route('admin.orders.show', $invoice->number))->assertOk()->assertSee('Hóa đơn đã thu được giữ nguyên')->assertSee('Cập nhật trạng thái đơn hàng')->assertDontSee('Quay về nhận order');
    }

    public function test_admin_cancellation_requires_reason_and_current_revision_and_is_audited(): void
    {
        $invoice = $this->invoice();
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $url = route('admin.counter-orders.update', $invoice);
        $this->actingAs($admin)->postJson(route('admin.counter-orders.preview', $invoice), ['target' => 'cancelled', 'revision' => 1])->assertUnprocessable();
        $stale = $this->confirmation($invoice, ['target' => 'cancelled', 'revision' => 1, 'reason' => 'Phiếu cũ']);
        $invoice->update(['revision' => 2]);
        $this->postJson($url, $stale)->assertConflict();
        $pay = $this->confirmation($invoice, ['target' => 'completed', 'revision' => 2]);
        $cancel = $this->confirmation($invoice, ['target' => 'cancelled', 'revision' => 2, 'reason' => 'Khách đổi ý']);
        $this->post($url, $cancel)->assertStatus(303);
        $this->assertSame('voided', $invoice->refresh()->status);
        $this->assertSame('cancelled', $invoice->order_status->value);
        $this->assertSame('Khách đổi ý', $invoice->round->events()->sole()->reason);
        $this->postJson($url, $pay)->assertConflict();
        $this->get(route('admin.orders.index', ['display_status' => 'cancelled']))->assertOk()->assertSee($invoice->number)->assertSee('order-state--cancelled', false);
    }

    public function test_status_write_is_admin_only_and_validates_target(): void
    {
        $invoice = $this->invoice();
        $url = route('admin.counter-orders.update', $invoice);
        $this->post($url, [])->assertRedirectToRoute('login');
        foreach ([UserRole::Staff, UserRole::Customer] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))->postJson($url, ['target' => 'completed', 'revision' => 1])->assertForbidden();
        }
        $this->actingAs(User::factory()->create(['role' => UserRole::Admin, 'is_active' => false]))->postJson($url, [])->assertForbidden();
        $this->actingAs(User::factory()->create(['role' => UserRole::Admin]))->postJson($url, ['target' => 'pending_payment', 'revision' => 1])->assertUnprocessable();
    }

    public function test_counter_revenue_uses_local_paid_day_and_actual_collection_channels(): void
    {
        $cash = $this->invoice('cash', true);
        $transfer = $this->invoice('bank_transfer', true);
        $draft = $this->invoice();
        $cancelled = $this->invoice();
        $cancelled->update(['status' => 'voided', 'order_status' => 'cancelled']);
        $cash->update(['paid_at' => '2026-11-20 17:00:00']);
        $transfer->update(['paid_at' => '2026-11-21 16:59:59']);
        $outside = $this->invoice('cash', true);
        $outside->update(['paid_at' => '2026-11-21 17:00:00']);
        $response = $this->actingAs(User::factory()->create(['role' => UserRole::Admin]))
            ->get(route('admin.dashboard', ['from' => '2026-11-21', 'to' => '2026-11-21']))->assertOk();
        $this->assertSame(80000, $response->viewData('totals')['total']);
        $this->assertSame(2, $response->viewData('totals')['orders']);
        $this->assertSame(['orders' => 1, 'total' => 40000], $response->viewData('channels')['cash']);
        $this->assertSame(['orders' => 1, 'total' => 40000], $response->viewData('channels')['bank_transfer']);
        $this->assertSame(80000, array_sum($response->viewData('dailyChannels')['2026-11-21']));
        $this->assertSame('draft', $draft->refresh()->status);
    }

    public function test_mixed_sources_reconcile_revenue_fees_and_cod_collected_by_transfer_without_double_counting(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-21 10:00:00', 'UTC'));
        $invoice = $this->invoice('cash', true);
        $invoice->update(['fees_vnd' => 5000, 'total_vnd' => 45000]);
        // A billed marker on a prepaid context must never produce a second legacy receipt.
        $invoice->tableSession->forceFill(['billed_at' => now(), 'paid_at' => now(), 'total_vnd' => 45000])->save();
        $customer = User::factory()->create();
        $order = $customer->orders()->create([
            'order_number' => 'HD0999999', 'checkout_key' => (string) Str::uuid(), 'request_hash' => hash('sha256', 'mixed'),
            'recipient_name' => 'Khách trực tuyến', 'phone' => '0901234567', 'fulfillment_type' => 'pickup', 'status' => 'completed',
            'subtotal_vnd' => 50000, 'discount_vnd' => 10000, 'delivery_fee_vnd' => 2000, 'total_vnd' => 42000,
        ]);
        $order->forceFill(['completed_at' => now()])->save();
        $payment = $order->payment()->create(['method' => 'cod', 'status' => 'paid', 'amount_vnd' => 42000]);
        $payment->forceFill(['collection_method' => 'bank_transfer', 'paid_at' => now()])->save();
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $legacy = app(TableSessions::class)->open($admin, ReservationSeat::where('code', 'T1-B02')->sole());
        $legacy->forceFill(['billing_mode' => 'legacy', 'billed_at' => now(), 'paid_at' => now(), 'subtotal_vnd' => 30000, 'fees_vnd' => 0, 'total_vnd' => 30000])->save();
        $this->actingAs($admin)->get(route('admin.orders.index'))->assertOk()
            ->assertSee($invoice->number)->assertSee($order->order_number)->assertSee($legacy->reference)
            ->assertViewHas('orders', fn ($orders) => $orders->total() === 3);
        $this->get(route('admin.orders.index', ['method' => 'bank_transfer']))->assertOk()
            ->assertViewHas('orders', fn ($orders) => $orders->total() === 1);
        $response = $this->get(route('admin.dashboard', ['from' => '2026-11-21', 'to' => '2026-11-21']))->assertOk();
        $totals = $response->viewData('totals');
        $this->assertSame(3, $totals['orders']);
        $this->assertSame(117000, $totals['total']);
        $this->assertSame($totals['total'], $totals['subtotal'] - $totals['discount'] + $totals['delivery'] + $response->viewData('feesTotal'));
        $this->assertSame(45000, $response->viewData('channels')['cash']['total']);
        $this->assertSame(42000, $response->viewData('channels')['bank_transfer']['total']);
        $this->assertSame(30000, $response->viewData('channels')['unclassified']['total']);
        $this->assertSame(1, $response->viewData('statuses')['completed']);
        $this->assertSame(2, (int) $response->viewData('counterStatuses')->get('completed'));
    }
}
