<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Category;
use App\Models\Ingredient;
use App\Models\Product;
use App\Models\ProductSize;
use App\Models\ReservationSeat;
use App\Models\TableInvoice;
use App\Models\User;
use App\Services\BestSellingProducts;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class TablePrepaymentTest extends TestCase
{
    use RefreshDatabase;

    private function start(): array
    {
        $this->actingAs(User::factory()->create(['role' => UserRole::Staff]));
        $seat = ReservationSeat::query()->where('code', 'T1-B01')->sole();
        $this->post(route('operations.table-sessions.open', $seat->code))->assertStatus(303);
        $session = $seat->activeTableSession()->sole();
        $this->assertSame('prepaid', $session->billing_mode);
        $category = Category::create(['name' => 'Cà phê', 'slug' => (string) Str::uuid()]);
        $product = $category->products()->create(['name' => 'Latte nóng', 'slug' => (string) Str::uuid()]);
        $size = $product->sizes()->create(['code' => 'm', 'label' => 'Vừa', 'price_vnd' => 40000]);
        $input = ['submission_key' => (string) Str::uuid(), 'session_reference' => $session->reference,
            'revision' => 0, 'method' => 'cash', 'intent' => 'draft', 'accepted_total_vnd' => 40000,
            'lines' => [['product_id' => $product->id, 'size_id' => $size->id, 'quantity' => 1]]];

        return [$session, $input];
    }

    public function test_draft_and_payment_are_independent_of_occupancy_and_payment_is_not_duplicated(): void
    {
        [$session, $input] = $this->start();
        $pos = route('operations.table-sessions.pos', $session);
        $save = route('operations.table-sessions.pos.store', $session);
        $this->get($pos)->assertOk()->assertSee('Latte nóng')->assertSee('Danh mục')->assertSee('Tìm món theo tên')
            ->assertSee('Lưu tạm tính')->assertSee('Hoàn tất thanh toán');
        $this->post($save, $input)->assertStatus(303);
        $invoice = TableInvoice::query()->sole();
        $round = $invoice->round;
        $this->get(route('operations.invoices.prepaid.show', $invoice))->assertOk()->assertSee('Phiếu tạm tính')->assertSee('Chưa thanh toán');
        $this->postJson(route('operations.table-sessions.advance', [$session, $round->id]), ['expected' => 'pending', 'target' => 'accepted'])->assertConflict();
        $pay = [...$input, 'round_id' => $round->id, 'revision' => 1, 'intent' => 'pay', 'payment_confirmed' => '1'];
        $this->postJson($save, [...$pay, 'payment_confirmed' => '0'])->assertUnprocessable();
        $this->post($save, $pay)->assertRedirectToRoute('operations.invoices.prepaid.show', $invoice);
        $this->post($save, $pay)->assertStatus(303);
        $this->assertSame('paid', $invoice->refresh()->status);
        $this->assertSame('completed', $round->refresh()->status->value);
        $this->assertSame('completed', $invoice->order_status->value);
        $this->assertSame(1, $round->events()->count());
        $this->assertSame('open', $session->refresh()->status->value);
        $this->assertSame('occupied', $session->seat->fresh()->availability());
        $this->get(route('operations.invoices.history', ['source' => 'table']))->assertOk()->assertSee($invoice->number);
        $this->postJson(route('operations.table-sessions.advance', [$session, $round->id]), ['expected' => 'completed', 'target' => 'preparing'])->assertConflict();
        $top = app(BestSellingProducts::class)->topFive(Product::query()->visibleInCatalog());
        $this->assertSame(1, (int) $top->sole()->cups_sold);
        $this->post(route('operations.table-sessions.update', [$session, 'close']))->assertStatus(303);
        $this->assertSame('free', $session->seat->fresh()->availability());
        $this->get(route('operations.invoices.prepaid.show', $invoice))->assertOk()->assertSee('Đã thanh toán');
    }

    public function test_changed_draft_requires_confirmation_keeps_original_and_rejects_stale_edit(): void
    {
        [$session, $input] = $this->start();
        $save = route('operations.table-sessions.pos.store', $session);
        $this->post($save, $input)->assertStatus(303);
        $invoice = TableInvoice::query()->sole();
        $changed = [...$input, 'round_id' => $invoice->table_round_id, 'revision' => 1,
            'lines' => [[...$input['lines'][0], 'quantity' => 2]], 'accepted_total_vnd' => 80000];
        $this->postJson($save, $changed)->assertConflict()->assertJsonValidationErrors('changes_confirmed');
        $this->assertSame(40000, $invoice->refresh()->total_vnd);
        $this->post($save, [...$changed, 'changes_confirmed' => 1])->assertStatus(303);
        $this->assertSame(2, $invoice->refresh()->revision);
        $this->assertSame(1, $invoice->initial_items[0]['quantity']);
        $this->assertSame(2, $invoice->round->items()->sole()->quantity);
        $this->assertDatabaseCount('table_invoice_revisions', 1);
        $this->postJson($save, [...$input, 'round_id' => $invoice->table_round_id, 'revision' => 1])->assertConflict();
        $pay = [...$changed, 'revision' => 2, 'intent' => 'pay', 'payment_confirmed' => '1'];
        $this->post($save, $pay)->assertStatus(303);
        $this->postJson($save, [...$pay, 'accepted_total_vnd' => 40000, 'lines' => $input['lines'], 'changes_confirmed' => 1])->assertConflict();
        $this->assertSame(80000, $invoice->refresh()->total_vnd);
        $this->post($save, [...$input, 'submission_key' => (string) Str::uuid()])->assertStatus(303);
        $this->assertDatabaseCount('table_invoices', 2);
    }

    public function test_guest_can_get_transfer_qr_and_report_but_cannot_mark_paid_or_read_other_table(): void
    {
        [$session, $input] = $this->start();
        config(['checkout.bank_transfer' => ['bank_name' => 'Ngân hàng thử', 'bank_bin' => '970436', 'account_number' => '0011001234567', 'account_holder' => 'QUAN THU']]);
        $seat = $session->seat;
        $input = [...$input, 'method' => 'bank_transfer'];
        Auth::forgetGuards();
        $this->post(route('table-orders.join', $seat->code), ['access_code' => $session->access_code])->assertStatus(303);
        $this->get(route('table-orders.pos', $seat->code))->assertOk()->assertSee('Latte nóng')->assertDontSee('Hoàn tất thanh toán');
        $this->post(route('table-orders.invoice.store', $seat->code), $input)->assertStatus(303);
        $invoice = TableInvoice::query()->sole();
        $show = route('table-orders.invoice.show', [$seat->code, $invoice]);
        $this->get($show)->assertOk()->assertSee('data:image/svg+xml;base64,')->assertSee('0011001234567')->assertSee($invoice->number);
        $this->post(route('table-orders.invoice.report', [$seat->code, $invoice]))->assertStatus(303);
        $this->assertNotNull($invoice->refresh()->payment_reported_at);
        $this->assertSame('draft', $invoice->status);
        $this->postJson(route('table-orders.invoice.store', $seat->code), [...$input, 'intent' => 'pay', 'payment_confirmed' => '1'])->assertForbidden();
        $this->get(route('table-orders.invoice.show', ['T2-B01', $invoice]))->assertForbidden();
        $this->get(route('operations.invoices.prepaid.show', $invoice))->assertRedirectToRoute('login');
        $this->actingAs(User::factory()->create(['role' => UserRole::Staff]));
        $this->post(route('operations.table-sessions.pos.store', $session), [...$input, 'round_id' => $invoice->table_round_id,
            'revision' => 1, 'intent' => 'pay', 'payment_confirmed' => '1'])->assertStatus(303);
        $this->assertSame('paid', $invoice->refresh()->status);
        $this->assertSame('bank_transfer', $invoice->method->value);
    }

    public function test_missing_bank_configuration_or_wrong_total_does_not_create_partial_orders(): void
    {
        config(['checkout.bank_transfer' => []]);
        [$session, $input] = $this->start();
        $save = route('operations.table-sessions.pos.store', $session);
        $this->postJson($save, [...$input, 'accepted_total_vnd' => 1])->assertConflict();
        $this->postJson($save, [...$input, 'method' => 'bank_transfer'])->assertConflict();
        $this->assertDatabaseCount('table_rounds', 0);
        $this->assertDatabaseCount('table_invoices', 0);
        $this->assertDatabaseHas('order_number_sequences', ['last_number' => 0]);
    }

    public function test_changes_are_confirmed_for_direct_payment_and_price_changes_cannot_be_silent(): void
    {
        [$session, $input] = $this->start();
        $save = route('operations.table-sessions.pos.store', $session);
        $this->post($save, $input)->assertStatus(303);
        $invoice = TableInvoice::query()->sole();
        ProductSize::query()->whereKey($input['lines'][0]['size_id'])->update(['price_vnd' => 45000]);
        $pay = [...$input, 'round_id' => $invoice->table_round_id, 'revision' => 1, 'intent' => 'pay', 'payment_confirmed' => 1];
        $this->postJson($save, $pay)->assertConflict()->assertJsonValidationErrors('table_invoice');
        $this->postJson($save, [...$pay, 'accepted_total_vnd' => 45000])->assertConflict()->assertJsonValidationErrors('changes_confirmed');
        $this->post($save, [...$pay, 'accepted_total_vnd' => 45000, 'changes_confirmed' => 1])->assertStatus(303);
        $this->assertSame(45000, $invoice->refresh()->total_vnd);
        $this->assertSame('paid', $invoice->status);
        $this->assertSame(40000, $invoice->initial_items[0]['line_total_vnd']);
    }

    public function test_reservation_fees_are_assigned_to_only_one_invoice_and_collected_once(): void
    {
        [$session, $input] = $this->start();
        $booking = User::factory()->create()->reservations()->create([
            'reservation_seat_id' => $session->reservation_seat_id, 'active_seat_id' => $session->reservation_seat_id,
            'code' => 'PREPAYMENT-FEES', 'booking_key' => (string) Str::uuid(), 'request_hash' => str_repeat('a', 64),
            'contact_name' => 'Khách thử', 'phone' => '0901234567', 'party_size' => 2, 'fee_channel' => 'counter',
            'status' => 'checked_in', 'scheduled_at' => now(), 'arrival_deadline' => now()->addMinutes(30),
        ]);
        $session->reservation_id = $booking->id;
        $session->save();
        $fee = $booking->fees()->create(['revision' => 1, 'kind' => 'reschedule', 'amount_vnd' => 10000, 'channel' => 'counter']);
        $save = route('operations.table-sessions.pos.store', $session);
        $this->post($save, [...$input, 'accepted_total_vnd' => 50000])->assertStatus(303);
        $first = TableInvoice::query()->sole();
        $this->post($save, [...$input, 'submission_key' => (string) Str::uuid()])->assertStatus(303);
        $this->assertSame($first->id, $fee->refresh()->table_invoice_id);
        $this->assertSame(50000, $first->total_vnd);
        $this->assertSame(40000, TableInvoice::query()->latest('id')->firstOrFail()->total_vnd);
        $pay = [...$input, 'round_id' => $first->table_round_id, 'accepted_total_vnd' => 50000, 'revision' => 1, 'intent' => 'pay', 'payment_confirmed' => 1];
        $this->post($save, $pay)->assertStatus(303);
        $this->post($save, $pay)->assertStatus(303);
        $this->assertSame('paid', $fee->refresh()->status);
        $this->assertSame(1, $booking->fees()->where('status', 'paid')->count());
    }

    public function test_new_invoice_failure_rolls_back_round_items_and_number_allocation(): void
    {
        [$session, $input] = $this->start();
        TableInvoice::created(static function (): never {
            throw new \RuntimeException('Invoice write failed');
        });
        $this->post(route('operations.table-sessions.pos.store', $session), $input)->assertServerError();
        $this->assertDatabaseCount('table_rounds', 0);
        $this->assertDatabaseCount('table_round_items', 0);
        $this->assertDatabaseCount('table_invoices', 0);
        $this->assertDatabaseHas('order_number_sequences', ['last_number' => 0]);
    }

    public function test_shop_qr_is_shared_and_the_seat_can_be_released_before_payment(): void
    {
        [$session, $input] = $this->start();
        $save = route('operations.table-sessions.pos.store', $session);
        $this->post($save, [...$input, 'method' => 'bank_transfer'])->assertStatus(303);
        $invoice = TableInvoice::query()->sole();
        $this->get(route('operations.invoices.prepaid.show', $invoice))->assertOk()
            ->assertSee('/payments/transfer-qr.png')->assertSee($invoice->number)->assertSee('Nhập đúng số tiền');
        $this->post(route('operations.table-sessions.update', [$session, 'close']))->assertStatus(303);
        $this->assertSame('free', $session->seat->fresh()->availability());
        $this->assertSame('draft', $invoice->refresh()->status);
        $this->post($save, [...$input, 'method' => 'bank_transfer', 'round_id' => $invoice->table_round_id,
            'revision' => 1, 'intent' => 'pay', 'payment_confirmed' => 1])->assertStatus(303);
        $this->assertSame('paid', $invoice->refresh()->status);
    }

    public function test_cancelling_an_unpaid_round_voids_its_draft_and_hides_payment_qr(): void
    {
        [$session, $input] = $this->start();
        $save = route('operations.table-sessions.pos.store', $session);
        $this->post($save, [...$input, 'method' => 'bank_transfer'])->assertStatus(303);
        $invoice = TableInvoice::query()->sole();
        $this->post(route('operations.table-sessions.advance', [$session, $invoice->table_round_id]),
            ['expected' => 'pending', 'target' => 'cancelled', 'reason' => 'Khách đổi ý trước khi thanh toán'])->assertStatus(303);
        $this->assertSame('voided', $invoice->refresh()->status);
        $this->assertSame('cancelled', $invoice->order_status->value);
        $this->get(route('operations.invoices.prepaid.show', $invoice))->assertOk()->assertSee('Đã hủy')->assertDontSee('/payments/transfer-qr.png');
        $this->get(route('operations.invoices.index'))->assertOk()->assertDontSee($invoice->number);
        $this->postJson($save, [...$input, 'method' => 'bank_transfer', 'round_id' => $invoice->table_round_id,
            'revision' => 1, 'intent' => 'pay', 'payment_confirmed' => 1])->assertConflict();
    }

    public function test_mysql_json_key_order_does_not_trigger_a_false_change_confirmation(): void
    {
        [$session, $input] = $this->start();
        $save = route('operations.table-sessions.pos.store', $session);
        $this->post($save, $input)->assertStatus(303);
        $invoice = TableInvoice::query()->sole();
        $invoice->selections = array_map(fn ($line) => array_reverse($line, true), $invoice->selections);
        $invoice->save();
        $this->post($save, [...$input, 'round_id' => $invoice->table_round_id, 'revision' => 1])->assertStatus(303)
            ->assertSessionHasNoErrors()->assertRedirectToRoute('operations.invoices.prepaid.show', $invoice);
        $this->assertDatabaseCount('table_invoice_revisions', 0);
        $this->post($save, [...$input, 'round_id' => $invoice->table_round_id, 'revision' => 1,
            'intent' => 'pay', 'payment_confirmed' => 1])->assertStatus(303)->assertSessionHasNoErrors();
        $this->assertSame('paid', $invoice->refresh()->status);
    }

    public function test_receipt_can_complete_its_saved_snapshot_without_resubmitting_the_menu(): void
    {
        [$session, $input] = $this->start();
        $this->post(route('operations.table-sessions.pos.store', $session), $input)->assertStatus(303);
        $invoice = TableInvoice::query()->sole();
        $show = route('operations.invoices.prepaid.show', $invoice);
        $pay = route('operations.invoices.prepaid.pay', $invoice);
        $this->get($show)->assertOk()->assertSee('Phiếu tạm tính')->assertSee('Hoàn tất thanh toán')->assertSee($pay)
            ->assertDontSee('data-table-pos')->assertDontSee('name="payment_confirmed"', false);
        ProductSize::query()->whereKey($input['lines'][0]['size_id'])->update(['price_vnd' => 99000]);
        $this->post($pay, ['revision' => 1])->assertRedirect($show)->assertSessionHasNoErrors();
        $this->post($pay, ['revision' => 1])->assertRedirect($show);
        $this->assertSame('paid', $invoice->refresh()->status);
        $this->assertSame(40000, $invoice->total_vnd);
        $this->assertSame(1, $invoice->round->events()->count());
        $this->actingAs(User::factory()->create())->postJson($pay, ['revision' => 1])->assertForbidden();
    }

    public function test_per_drink_notes_are_saved_validated_and_require_confirmation_when_changed(): void
    {
        [$session, $input] = $this->start();
        $save = route('operations.table-sessions.pos.store', $session);
        $input['lines'][0]['notes'] = 'Ít ngọt, để riêng đá <script>test</script>';
        $this->postJson($save, [...$input, 'lines' => [[...$input['lines'][0], 'notes' => str_repeat('x', 501)]]])->assertUnprocessable()->assertJsonValidationErrors('lines.0.notes');
        $this->post($save, $input)->assertStatus(303);
        $invoice = TableInvoice::query()->sole();
        $this->assertSame($input['lines'][0]['notes'], $invoice->round->items()->sole()->notes);
        $this->get(route('operations.invoices.prepaid.show', $invoice))->assertOk()->assertSee('Ít ngọt, để riêng đá')
            ->assertDontSee('<script>test</script>', false);
        $this->get(route('operations.table-sessions.show', $session))->assertRedirectToRoute('operations.table-sessions.index');
        $changed = [...$input, 'round_id' => $invoice->table_round_id, 'revision' => 1,
            'lines' => [[...$input['lines'][0], 'notes' => 'Không đường']]];
        $this->postJson($save, $changed)->assertConflict()->assertJsonValidationErrors('changes_confirmed');
        $this->post($save, [...$changed, 'changes_confirmed' => 1])->assertStatus(303);
        $this->assertSame('Không đường', $invoice->round->items()->sole()->notes);
        $this->assertSame($input['lines'][0]['notes'], $invoice->refresh()->initial_items[0]['notes']);
        $this->postJson(route('operations.invoices.prepaid.pay', $invoice), ['revision' => 1])->assertConflict();
        $this->post(route('operations.invoices.prepaid.pay', $invoice), ['revision' => 2])->assertStatus(303);
        $this->assertSame('paid', $invoice->refresh()->status);
    }

    public function test_saving_initializes_a_pending_order_and_payment_completes_without_preparation(): void
    {
        [$session, $input] = $this->start();
        $this->assertDatabaseCount('table_invoices', 0);
        $save = route('operations.table-sessions.pos.store', $session);
        $this->post($save, $input)->assertStatus(303);
        $invoice = TableInvoice::query()->sole();
        $this->assertSame('pending_payment', $invoice->order_status->value);
        $this->get(route('operations.invoices.prepaid.show', $invoice))->assertOk()->assertSee('Trạng thái đơn:')->assertSee('Chờ thanh toán');
        $this->get(route('operations.table-sessions.index'))->assertOk()->assertSee('Nhận order')->assertDontSee('Món đang phục vụ');
        $this->post(route('operations.invoices.prepaid.pay', $invoice), ['revision' => 1])->assertStatus(303);
        $this->assertSame('completed', $invoice->refresh()->order_status->value);
        $this->get(route('operations.invoices.prepaid.show', $invoice))->assertOk()->assertSee('Hoàn tất')->assertSee('Quay về nhận order')->assertSee(route('operations.table-sessions.index'));
        $this->postJson(route('operations.table-sessions.advance', [$session, $invoice->table_round_id]), ['expected' => 'completed', 'target' => 'preparing'])->assertConflict();
    }

    public function test_counter_payment_consumes_saved_inventory_once_and_shortage_rolls_back_completion(): void
    {
        [$session, $input] = $this->start();
        $ingredient = Ingredient::create(['name' => 'Sữa thử', 'unit' => 'ml', 'low_threshold' => 0]);
        $ingredient->stock = 25000;
        $ingredient->stock_version = 1;
        $ingredient->save();
        ProductSize::query()->findOrFail($input['lines'][0]['size_id'])->recipeIngredients()->attach($ingredient, ['quantity' => 10000]);
        DB::table('inventory_settings')->where('id', 1)->update(['enabled' => true]);
        $save = route('operations.table-sessions.pos.store', $session);
        $this->post($save, $input)->assertStatus(303);
        $invoice = TableInvoice::query()->sole();
        $this->assertSame(25000, $ingredient->refresh()->stock);
        $pay = route('operations.invoices.prepaid.pay', $invoice);
        $this->post($pay, ['revision' => 1])->assertStatus(303);
        $this->post($pay, ['revision' => 1])->assertStatus(303);
        $this->assertSame(15000, $ingredient->refresh()->stock);
        $this->assertDatabaseCount('inventory_movements', 1);
        $this->postJson($save, [...$input, 'submission_key' => (string) Str::uuid(), 'intent' => 'pay', 'payment_confirmed' => 1,
            'accepted_total_vnd' => 80000, 'lines' => [[...$input['lines'][0], 'quantity' => 2]]])->assertConflict()->assertJsonValidationErrors('inventory');
        $this->assertDatabaseCount('table_invoices', 1);
        $this->assertDatabaseCount('table_rounds', 1);
        $this->assertSame(15000, $ingredient->refresh()->stock);
        $this->assertDatabaseCount('inventory_movements', 1);
    }
}
