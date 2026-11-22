<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\ReservationSeat;
use App\Models\TableSession;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class OperationsInvoicesTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_active_operators_can_read_invoices_and_get_does_not_collect_money(): void
    {
        $order = $this->order();
        $table = $this->table();
        $urls = [route('operations.invoices.index'), route('operations.invoices.history'),
            route('operations.invoices.orders.show', $order->order_number), route('operations.invoices.tables.show', $table)];
        foreach ($urls as $url) {
            $this->get($url)->assertRedirectToRoute('login');
        }
        foreach ([User::factory()->create(), User::factory()->create(['role' => UserRole::Staff, 'is_active' => false])] as $user) {
            foreach ($urls as $url) {
                $this->actingAs($user)->get($url)->assertForbidden();
            }
        }
        $before = $table->fresh()->getRawOriginal();
        foreach ([UserRole::Staff, UserRole::Admin] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]));
            foreach ($urls as $url) {
                $this->get($url)->assertOk()->assertHeader('Cache-Control', 'no-store, private');
            }
        }
        $this->assertSame($before, $table->fresh()->getRawOriginal());
        $this->assertNull($order->payment()->sole()->paid_at);
    }

    public function test_history_filters_payment_dates_at_vietnamese_midnight_for_both_sources(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-22 12:00:00', 'UTC'));
        $before = $this->order('2026-11-21 16:59:59');
        $start = $this->order('2026-11-21 17:00:00');
        $last = $this->order('2026-11-22 16:59:59', 'cancelled', 'refund_pending');
        $after = $this->order('2026-11-22 17:00:00');
        $table = $this->table('2026-11-22 08:00:00', 'closed');
        $this->actingAs($this->staff());
        $url = route('operations.invoices.history', ['from' => '2026-11-22', 'to' => '2026-11-22']);
        $page = $this->get($url)->assertOk()->assertSee($start->order_number)->assertSee($last->order_number)
            ->assertDontSee($before->order_number)->assertDontSee($after->order_number)
            ->assertSee(route('operations.invoices.tables.show', $table))->assertSee('Chờ hoàn tiền')
            ->assertViewHas('bills', fn ($bills) => $bills->total() === 3);
        $page->assertSee('00:00 22/11/2026')->assertSee('23:59 22/11/2026');
        $this->get($url.'&source=table')->assertOk()->assertViewHas('bills', fn ($bills) => $bills->total() === 1)->assertDontSee($start->order_number);
        $this->get($url.'&source=order')->assertOk()->assertViewHas('bills', fn ($bills) => $bills->total() === 2)
            ->assertDontSee(route('operations.invoices.tables.show', $table));
    }

    public function test_waiting_list_only_contains_unpaid_active_orders_and_billed_tables(): void
    {
        $pending = $this->order();
        $paid = $this->order(now()->toDateTimeString());
        $cancelled = $this->order(status: 'cancelled', paymentStatus: 'voided');
        $table = $this->table();
        $unbilled = $this->table();
        $unbilled->billed_at = null;
        $unbilled->save();
        $this->actingAs($this->staff())->get(route('operations.invoices.index'))->assertOk()
            ->assertSee($pending->order_number)->assertDontSee($paid->order_number)->assertDontSee($cancelled->order_number)
            ->assertSee(route('operations.invoices.tables.show', $table))->assertDontSee(route('operations.invoices.tables.show', $unbilled));
        $this->get(route('operations.invoices.tables.show', $unbilled))->assertNotFound();
        $this->get(route('operations.invoices.orders.show', 'MISSING'))->assertNotFound();
    }

    public function test_order_invoice_uses_snapshot_names_and_totals_without_changing_payment(): void
    {
        $order = $this->order();
        $category = Category::create(['name' => 'Cà phê', 'slug' => 'coffee']);
        $product = Product::create(['category_id' => $category->id, 'name' => 'Tên món hiện tại', 'slug' => 'current']);
        $size = $product->sizes()->create(['code' => 'm', 'label' => 'Size mới', 'price_vnd' => 999999]);
        $order->items()->create(['product_size_id' => $size->id, 'product_name' => 'Tên món lúc bán', 'size_label' => 'Size lúc bán',
            'quantity' => 1, 'base_unit_price_vnd' => 40000, 'line_total_vnd' => 40000]);
        $this->actingAs($this->staff())->get(route('operations.invoices.orders.show', $order->order_number))->assertOk()
            ->assertSee('Tên món lúc bán')->assertSee('Size lúc bán')->assertSee('40.000 ₫')->assertSee('In hóa đơn')
            ->assertDontSee('Tên món hiện tại')->assertDontSee('999.999');
        $this->assertSame('pending', $order->payment()->sole()->status->value);
    }

    public function test_table_invoice_only_prints_served_rounds_and_keeps_the_saved_bill_after_closing(): void
    {
        $table = $this->table(now()->toDateTimeString(), 'closed');
        $category = Category::create(['name' => 'Cà phê', 'slug' => 'coffee']);
        $product = Product::create(['category_id' => $category->id, 'name' => 'Món', 'slug' => 'drink']);
        $size = $product->sizes()->create(['code' => 'm', 'label' => 'Vừa', 'price_vnd' => 50000]);
        foreach (['served' => 'Món đã phục vụ', 'cancelled' => 'Món đã hủy'] as $status => $name) {
            $round = $table->rounds()->create(['submission_key' => Str::uuid(), 'request_hash' => str_repeat('a', 64),
                'status' => $status, 'total_vnd' => 50000, 'inventory_snapshot' => ['quantities' => [], 'missing' => []]]);
            $round->items()->create(['product_size_id' => $size->id, 'product_name' => $name, 'size_label' => 'Vừa',
                'quantity' => 1, 'toppings' => [], 'unit_price_vnd' => 50000, 'line_total_vnd' => 50000]);
        }
        $this->actingAs($this->staff())->get(route('operations.invoices.tables.show', $table))->assertOk()
            ->assertSee('Món đã phục vụ')->assertDontSee('Món đã hủy')->assertSee('60.000 ₫')->assertSee('10.000 ₫')
            ->assertDontSee('12345678');
    }

    public function test_history_paginates_and_keeps_the_date_and_source_filters(): void
    {
        for ($i = 0; $i < 16; $i++) {
            $this->order('2026-11-22 08:00:00');
        }
        $filters = ['from' => '2026-11-22', 'to' => '2026-11-22', 'source' => 'order'];
        $this->actingAs($this->staff())->get(route('operations.invoices.history', $filters))->assertOk()
            ->assertViewHas('bills', fn ($bills) => $bills->total() === 16 && $bills->count() === 15)
            ->assertSee('source=order')->assertSee('from=2026-11-22')->assertSee('page=2');
        $this->get(route('operations.invoices.history', [...$filters, 'page' => 2]))->assertOk()
            ->assertViewHas('bills', fn ($bills) => $bills->count() === 1);
    }

    public function test_invalid_filters_are_rejected_on_the_server(): void
    {
        $this->actingAs($this->staff());
        foreach ([['from' => 'bad', 'to' => '2026-11-22'], ['from' => '2026-11-22'],
            ['from' => '2026-11-23', 'to' => '2026-11-22'], ['from' => '2020-01-01', 'to' => '2026-11-22'],
            ['source' => 'other'], ['page' => 0]] as $filters) {
            $this->getJson(route('operations.invoices.history', $filters))->assertUnprocessable();
        }
    }

    private function staff(): User
    {
        return User::factory()->create(['role' => UserRole::Staff]);
    }

    private function order(?string $paidAt = null, string $status = 'confirmed', ?string $paymentStatus = null): Order
    {
        $order = User::factory()->create()->orders()->create([
            'order_number' => 'CF-'.Str::ulid(), 'checkout_key' => Str::uuid(), 'request_hash' => str_repeat('a', 64),
            'recipient_name' => 'Khách nhận', 'phone' => '0901234567', 'fulfillment_type' => 'pickup',
            'status' => $status, 'subtotal_vnd' => 40000, 'discount_vnd' => 0, 'delivery_fee_vnd' => 0, 'total_vnd' => 40000,
        ]);
        $payment = $order->payment()->create(['method' => 'cod', 'status' => $paymentStatus ?? ($paidAt ? 'paid' : 'pending'), 'amount_vnd' => 40000]);
        if ($paidAt !== null) {
            $payment->paid_at = CarbonImmutable::parse($paidAt, 'UTC');
            $payment->save();
        }

        return $order;
    }

    private function table(?string $paidAt = null, string $status = 'closing'): TableSession
    {
        return TableSession::query()->forceCreate([
            'reference' => (string) Str::uuid(), 'reservation_seat_id' => ReservationSeat::query()->firstOrFail()->id,
            'opened_by' => $this->staff()->id, 'access_code' => '12345678', 'access_digest' => hash('sha256', Str::uuid()), 'status' => $status,
            'billed_at' => now()->subHour(), 'subtotal_vnd' => 50000, 'fees_vnd' => 10000, 'total_vnd' => 60000,
            'paid_at' => $paidAt === null ? null : CarbonImmutable::parse($paidAt, 'UTC'),
        ]);
    }
}
