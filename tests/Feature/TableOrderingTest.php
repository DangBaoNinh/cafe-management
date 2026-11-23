<?php

namespace Tests\Feature;

use App\Enums\ReservationStatus;
use App\Enums\TableRoundStatus;
use App\Enums\TableSessionStatus;
use App\Enums\UserRole;
use App\Models\Category;
use App\Models\Ingredient;
use App\Models\Product;
use App\Models\ProductSize;
use App\Models\Reservation;
use App\Models\ReservationSeat;
use App\Models\TableRound;
use App\Models\TableSession;
use App\Models\Topping;
use App\Models\User;
use App\Services\BestSellingProducts;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\BestSellerFixture;
use Tests\TestCase;

class TableOrderingTest extends TestCase
{
    use BestSellerFixture;
    use RefreshDatabase;

    public function test_guest_and_staff_order_in_the_same_walk_in_session_without_duplicate_or_cross_table_sales(): void
    {
        $staff = $this->staff();
        [$product, $size, $topping] = $this->catalog();
        $seat = $this->seat('T1-B01');
        $other = $this->seat('T2-PH');

        $this->get(route('operations.table-sessions.index'))->assertRedirectToRoute('login');
        $this->actingAs(User::factory()->create())->postJson(route('operations.table-sessions.open', $seat->code))->assertForbidden();
        $this->actingAs($staff)->get(route('operations.table-sessions.index'))->assertOk()->assertSee('Nhận order');
        $session = $this->open($seat);
        $otherSession = $this->open($other);
        $this->postJson(route('operations.table-sessions.open', $seat->code))->assertConflict();
        $this->get(route('operations.table-sessions.qr', $seat->code))->assertOk()
            ->assertHeader('Content-Type', 'image/svg+xml; charset=UTF-8')->assertSee('<svg', false);
        $this->get(route('operations.table-sessions.show', $session))->assertOk()->assertSee($session->access_code);
        $this->assertSame('occupied', $seat->fresh()->availability());
        Auth::forgetGuards();

        $show = route('table-orders.show', $seat->code);
        $this->get($show)->assertOk()->assertSee('Mã phiên do nhân viên cung cấp')->assertDontSee($session->access_code);
        $this->postJson(route('table-orders.join', $other->code), ['access_code' => $session->access_code])
            ->assertUnprocessable()->assertJsonValidationErrors('access_code');
        $this->join($seat, $session);
        $this->get($show)->assertOk()->assertSee('Yêu cầu tính tiền')->assertSee($product->name);
        $this->assertGuest();

        $line = $this->line($product, $size, $topping, 2);
        $payload = $this->payload($session, [$line, $this->line($product, $size, quantity: 1)]);
        $this->post(route('table-orders.store', $seat->code), $payload)->assertRedirectToRoute('table-orders.show', $seat->code);
        $this->post(route('table-orders.store', $seat->code), $payload)->assertStatus(303);
        $this->postJson(route('table-orders.store', $seat->code), [...$payload, 'lines' => [$line]])->assertConflict();
        $this->postJson(route('table-orders.store', $other->code), $this->payload($otherSession, [$line]))->assertForbidden();
        $this->get(route('table-orders.show', $other->code))->assertOk()->assertSee('Vào phiên gọi món')->assertDontSee('Lượt đã gửi');
        $first = $session->rounds()->sole();
        $this->assertSame(130000, $first->total_vnd);
        $this->assertSame([2, 1], $first->items()->orderBy('id')->pluck('quantity')->all());

        $staffPayload = $this->payload($session, [$line]);
        $this->actingAs($staff)->post(route('operations.table-sessions.store', $session), $staffPayload)->assertStatus(303);
        $this->post(route('operations.table-sessions.store', $session), $staffPayload)->assertStatus(303);
        $rounds = $session->rounds()->orderBy('id')->get();
        $this->assertCount(2, $rounds);
        $this->assertSame($staff->id, $rounds[1]->entered_by);
        $this->get(route('operations.table-sessions.show', $session))->assertOk()->assertViewHas('rounds', fn ($items) => $items->count() === 2);
        $this->postJson(route('operations.table-sessions.advance', [$otherSession, $first->id]), [
            'expected' => 'pending', 'target' => 'accepted',
        ])->assertNotFound();

        Auth::forgetGuards();
        $this->get($show)->assertOk()->assertSeeInOrder(['Lượt '.$first->id, 'Lượt '.$rounds[1]->id]);
        $this->join($other, $otherSession);
        $this->get($show)->assertOk()->assertDontSee('Lượt '.$first->id);
        $this->postJson(route('table-orders.store', $seat->code), $payload)->assertForbidden();
    }

    public function test_checkout_collects_only_served_drinks_and_outstanding_counter_fees_then_releases_on_departure(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-22 11:00:00', 'Asia/Ho_Chi_Minh')->utc());
        $staff = $this->staff();
        $seat = $this->seat('T2-PH');
        [$product, $size] = $this->catalog();
        $booking = $this->booking($seat);
        $this->actingAs($staff);
        $this->post(route('operations.reservations.check-in', $booking->code))->assertStatus(303);
        $offline = $booking->fees()->create(['revision' => 1, 'kind' => 'reschedule', 'amount_vnd' => 10000, 'channel' => 'counter']);
        $alreadyPaid = $booking->fees()->create(['revision' => 2, 'kind' => 'reschedule', 'amount_vnd' => 10000, 'channel' => 'app']);
        $alreadyPaid->status = 'paid';
        $alreadyPaid->save();
        $online = $booking->fees()->create([
            'revision' => 3, 'kind' => 'reschedule', 'amount_vnd' => 10000, 'channel' => 'app', 'transfer_code' => 'PHIONLINE',
        ]);
        $failed = $booking->fees()->create(['revision' => 4, 'kind' => 'reschedule', 'amount_vnd' => 10000, 'channel' => 'app']);
        $failed->status = 'failed';
        $failed->transfer_result = 'expired';
        $failed->save();
        $session = $this->open($seat);
        $this->assertSame($booking->id, $session->reservation_id);
        $oldCode = $session->access_code;
        $this->postJson(route('operations.reservations.release', $booking->code))->assertUnprocessable();
        $this->postJson(route('operations.reservations.collect', $booking->code), ['amount_vnd' => 10000])->assertUnprocessable();
        Auth::forgetGuards();
        $this->join($seat, $session);
        $item = $this->line($product, $size);
        $original = $this->payload($session, [$item]);
        $store = route('table-orders.store', $seat->code);
        $this->post($store, $original)->assertStatus(303);
        $this->post($store, $this->payload($session, [$item]))->assertStatus(303);
        [$served, $cancelled] = $session->rounds()->orderBy('id')->get()->all();
        $size->update(['price_vnd' => 50000]);
        $this->post(route('table-orders.checkout', $seat->code), ['session_reference' => $session->reference])->assertStatus(303);

        $this->actingAs($staff)->get(route('operations.table-sessions.show', $session))->assertOk()->assertSee('Khách đã yêu cầu tính tiền.');
        $this->advance($session, $served, 'pending', 'accepted');
        $this->advance($session, $served, 'accepted', 'preparing');
        $this->postJson($this->action($session, 'close'))->assertConflict();
        $this->post($this->action($session, 'lock'))->assertStatus(303);
        $this->postJson(route('operations.table-sessions.store', $session), $this->payload($session, [$item]))->assertConflict();
        $this->postJson($this->action($session, 'bill'))->assertConflict();
        $this->postJson($this->action($session, 'pay'), ['amount_vnd' => 50000])->assertConflict();

        Auth::forgetGuards();
        $this->postJson($store, $this->payload($session, [$item]))->assertConflict();
        $this->post($store, $original)->assertStatus(303);
        $this->get(route('table-orders.show', $seat->code))->assertOk()->assertSee('Đã khóa gọi thêm')->assertDontSee('Gửi lượt gọi');

        $this->actingAs($staff);
        $this->advance($session, $served, 'preparing', 'ready');
        $this->advance($session, $served, 'ready', 'served');
        $this->postJson(route('operations.table-sessions.advance', [$session, $cancelled->id]), ['expected' => 'pending', 'target' => 'cancelled'])->assertConflict();
        $this->advance($session, $cancelled, 'pending', 'cancelled', 'Khách đổi ý');
        $this->assertSame('Khách đổi ý', $cancelled->events()->sole()->reason);
        $this->postJson($this->action($session, 'bill'))->assertConflict()
            ->assertJsonPath('errors.table_session.0', 'Còn phụ phí qua ứng dụng chưa đối chiếu. Hãy xử lý trên lượt đặt chỗ trước khi chốt.');
        $this->post(route('operations.reservations.resolve', [$booking->code, $online->id]), ['result' => 'expired'])->assertStatus(303);
        $this->post(route('operations.reservations.counter', [$booking->code, $online->id]))->assertStatus(303);
        $this->post($this->action($session, 'bill'))->assertStatus(303);
        $this->assertSame([40000, 20000, 60000], [$session->refresh()->subtotal_vnd, $session->fees_vnd, $session->total_vnd]);
        $this->assertSame([$offline->id, $online->id], $session->fees()->orderBy('id')->pluck('id')->all());
        $this->assertNull($alreadyPaid->fresh()->table_session_id);
        $this->postJson(route('operations.reservations.counter', [$booking->code, $failed->id]))->assertUnprocessable();
        $this->postJson($this->action($session, 'pay'), ['amount_vnd' => 59999])->assertConflict();
        $this->assertNull($session->fresh()->paid_at);
        $this->assertSame('pending', $offline->fresh()->status);
        $this->freezeSecond();
        $this->post($this->action($session, 'pay'), ['amount_vnd' => 60000])->assertStatus(303);
        $firstPayment = $session->refresh()->paid_at;
        $this->travel(1)->minutes();
        $this->actingAs($this->staff())->post($this->action($session, 'pay'), ['amount_vnd' => 60000])->assertStatus(303);
        $this->assertEquals($firstPayment, $session->refresh()->paid_at);
        $this->assertSame($staff->id, $session->paid_by);
        $this->assertSame('paid', $offline->fresh()->status);
        $this->assertSame('occupied', $seat->fresh()->availability());
        $this->get(route('operations.table-sessions.show', $session))->assertOk()->assertSee('Tổng đã thanh toán')->assertSee('60.000');
        $this->post($this->action($session, 'close'))->assertStatus(303);
        $this->assertSame(TableSessionStatus::Closed, $session->refresh()->status);
        $this->assertNull($session->access_code);
        $this->assertSame('free', $seat->fresh()->availability());
        $this->assertSame(ReservationStatus::Completed, $booking->fresh()->status);
        $this->get(route('operations.invoices.history', ['source' => 'table']))->assertOk()->assertSee('60.000');

        $newSession = $this->open($seat);
        Auth::forgetGuards();
        $this->postJson(route('table-orders.join', $seat->code), ['access_code' => $oldCode])->assertUnprocessable();
        $this->postJson($store, $original)->assertForbidden();
        $this->join($seat, $newSession);
        $this->postJson($store, $original)->assertConflict();
        $this->get(route('table-orders.show', $seat->code))->assertOk()->assertDontSee('Lượt '.$served->id);
    }

    public function test_preparation_consumes_saved_recipes_once_and_best_sellers_count_only_paid_served_rounds(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-22 10:00:00', 'Asia/Ho_Chi_Minh')->utc());
        $staff = $this->staff();
        $seat = $this->seat('T1-B03');
        [$product, $size, $topping] = $this->catalog();
        $category = $product->category;
        $second = $this->makeCatalogProduct($category, 'Cà phê thứ hai');
        $milk = Ingredient::create(['name' => 'Sữa', 'unit' => 'ml', 'low_threshold' => 0]);
        $milk->stock = 25000;
        $milk->stock_version = 1;
        $milk->save();
        $size->recipeIngredients()->attach($milk, ['quantity' => 10000]);
        $topping->recipeIngredients()->attach($milk, ['quantity' => 2500]);
        $second->sizes()->sole()->recipeIngredients()->attach($milk, ['quantity' => 10000]);
        DB::table('inventory_settings')->where('id', 1)->update(['enabled' => true]);

        $this->actingAs($staff);
        $session = $this->open($seat);
        $this->post(route('operations.table-sessions.store', $session), $this->payload($session, [$this->line($product, $size, $topping, 2)]))->assertStatus(303);
        $this->post(route('operations.table-sessions.store', $session), $this->payload($session, [$this->line($second, $second->sizes()->sole())]))->assertStatus(303);
        [$served, $short] = $session->rounds()->orderBy('id')->get()->all();
        $size->recipeIngredients()->updateExistingPivot($milk, ['quantity' => 50000]);
        $this->advance($session, $served, 'pending', 'accepted');
        $this->advance($session, $served, 'accepted', 'preparing');
        $this->postJson(route('operations.table-sessions.advance', [$session, $served->id]), [
            'expected' => 'accepted', 'target' => 'preparing',
        ])->assertConflict();
        $this->assertSame(0, $milk->fresh()->stock);
        $this->assertDatabaseHas('inventory_movements', ['table_round_id' => $served->id, 'quantity_change' => -25000, 'order_id' => null]);
        $this->advance($session, $short, 'pending', 'accepted');
        $this->postJson(route('operations.table-sessions.advance', [$session, $short->id]), [
            'expected' => 'accepted', 'target' => 'preparing',
        ])->assertConflict()->assertJsonValidationErrors('inventory');
        $this->assertSame(TableRoundStatus::Accepted, $short->fresh()->status);
        $this->advance($session, $short, 'accepted', 'cancelled', 'Thiếu nguyên liệu');
        $this->advance($session, $served, 'preparing', 'ready');
        $this->advance($session, $served, 'ready', 'served');
        $this->post($this->action($session, 'lock'))->assertStatus(303);
        $this->post($this->action($session, 'bill'))->assertStatus(303);
        $ranking = app(BestSellingProducts::class);
        $this->assertSame([], $ranking->topFive(Product::query()->visibleInCatalog())->modelKeys());
        $this->recordSale(User::factory()->create(), now()->toDateTimeString(), [[$second->sizes()->sole(), 3]]);
        $this->post($this->action($session, 'pay'), ['amount_vnd' => 90000])->assertStatus(303);
        $this->postJson(route('operations.reservations.walk-in', $seat->id), ['arriving' => false])->assertUnprocessable();
        $this->post($this->action($session, 'close'))->assertStatus(303);
        $this->assertSame([$second->id, $product->id], $ranking->topFive(Product::query()->visibleInCatalog())->modelKeys());
        $this->assertSame('free', $seat->fresh()->availability());
        $this->assertSame(1, $milk->movements()->count());
    }

    public function test_customer_input_is_repriced_and_code_attempts_are_limited_without_revealing_sessions(): void
    {
        $seat = $this->seat('T1-B04');
        [$product, $size, $topping] = $this->catalog();
        $staff = $this->staff();
        $this->actingAs($staff);
        $session = $this->open($seat);
        Auth::forgetGuards();
        $join = route('table-orders.join', $seat->code);
        $submit = route('table-orders.store', $seat->code);
        $this->join($seat, $session);
        $invalidTopping = [...$this->line($product, $size), 'topping_ids' => [$topping->id + 100]];
        $this->postJson($submit, $this->payload($session, [$invalidTopping]))->assertUnprocessable()->assertJsonValidationErrors('lines');
        $product->update(['is_active' => false]);
        $this->postJson($submit, $this->payload($session, [$this->line($product, $size)]))->assertUnprocessable()->assertJsonValidationErrors('lines');
        $this->assertFalse($session->rounds()->exists());

        $wrongCode = $session->access_code === '00000000' ? '11111111' : '00000000';
        for ($i = 0; $i < 5; $i++) {
            $this->postJson($join, ['access_code' => $wrongCode])->assertUnprocessable();
        }
        $this->postJson($join, ['access_code' => $wrongCode])->assertTooManyRequests();
    }

    private function staff(): User
    {
        return User::factory()->create(['role' => UserRole::Staff]);
    }

    private function seat(string $code): ReservationSeat
    {
        return ReservationSeat::query()->where('code', $code)->sole();
    }

    private function open(ReservationSeat $seat): TableSession
    {
        $this->post(route('operations.table-sessions.open', $seat->code))->assertStatus(303);

        // These regression scenarios represent sessions opened before prepaid billing was introduced.
        $session = $seat->activeTableSession()->sole();
        $session->billing_mode = 'legacy';
        $session->save();

        return $session;
    }

    private function join(ReservationSeat $seat, TableSession $session): void
    {
        $this->post(route('table-orders.join', $seat->code), ['access_code' => $session->access_code])
            ->assertStatus(303)->assertSessionHas('table_order_session', $session->reference);
    }

    /** @return array{Product, ProductSize, Topping} */
    private function catalog(): array
    {
        $category = Category::create(['name' => 'Cà phê tại quán', 'slug' => 'cafe-quan']);
        $product = $category->products()->create(['name' => 'Latte tại bàn', 'slug' => 'latte-tai-ban']);
        $size = $product->sizes()->create(['code' => 'm', 'label' => 'Vừa', 'price_vnd' => 40000]);
        $topping = Topping::create(['code' => 'foam', 'name' => 'Kem sữa', 'price_vnd' => 5000]);
        $product->toppings()->attach($topping);

        return [$product, $size, $topping];
    }

    private function line(Product $product, ProductSize $size, ?Topping $topping = null, int $quantity = 1): array
    {
        return [
            'product_id' => $product->id, 'size_id' => $size->id, 'quantity' => $quantity,
            'topping_ids' => $topping === null ? [] : [$topping->id],
        ];
    }

    private function payload(TableSession $session, array $lines): array
    {
        return ['session_reference' => $session->reference, 'submission_key' => Str::uuid()->toString(), 'lines' => $lines];
    }

    private function advance(TableSession $session, TableRound $round, string $expected, string $target, ?string $reason = null): void
    {
        $this->post(route('operations.table-sessions.advance', [$session, $round->id]), compact('expected', 'target', 'reason'))->assertStatus(303);
    }

    private function action(TableSession $session, string $action): string
    {
        return route('operations.table-sessions.update', [$session, $action]);
    }

    private function booking(ReservationSeat $seat): Reservation
    {
        return User::factory()->create()->reservations()->create([
            'reservation_seat_id' => $seat->id, 'active_seat_id' => $seat->id, 'code' => 'A01B02C03D04E05F',
            'booking_key' => Str::uuid()->toString(), 'request_hash' => hash('sha256', 'booking'),
            'contact_name' => 'Khách đặt trước', 'phone' => '0901234567', 'party_size' => 2,
            'fee_channel' => 'counter', 'status' => ReservationStatus::Confirmed, 'scheduled_at' => now(),
            'arrival_deadline' => now()->addMinutes(30),
        ]);
    }
}
