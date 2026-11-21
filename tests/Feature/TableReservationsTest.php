<?php

namespace Tests\Feature;

use App\Enums\ReservationStatus;
use App\Enums\UserRole;
use App\Models\Category;
use App\Models\Reservation;
use App\Models\ReservationSeat;
use App\Models\User;
use App\Services\BookingRules;
use App\Services\ShoppingCart;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class TableReservationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_map_inventory_and_availability_include_held_seats_and_walk_ins(): void
    {
        $this->at('06:00');
        $guest = User::factory()->create();
        $staff = User::factory()->create(['role' => UserRole::Staff]);
        $this->actingAs($guest)->get(route('reservations.create'))->assertOk()->assertSee('Tầng 1')->assertSee('Tầng 2')
            ->assertSeeHtml('value="07:00"')->assertSeeHtml('value="22:30"')->assertDontSeeHtml('value="22:35"')->assertDontSeeHtml('value="07:01"');
        foreach ([1, 2] as $floor) {
            $this->assertSame([2 => 5, 4 => 5, 10 => 1], ReservationSeat::query()->where('floor', $floor)
                ->orderBy('capacity')->get()->countBy('capacity')->all());
        }
        $this->book($guest, 'T1-B01', '07:00');
        $walkIn = $this->seat('T1-B02');
        $walkInRoute = route('operations.reservations.walk-in', $walkIn->id);
        $this->actingAs($staff)->post($walkInRoute, ['arriving' => '1'])->assertStatus(303);
        $this->get(route('operations.reservations.seats'))->assertOk()->assertSee('Trả chỗ khách trực tiếp');
        $this->postJson(route('operations.reservations.seat-active', $walkIn->id), ['active' => false])->assertForbidden();
        $filters = ['pickup_date' => '2026-11-21', 'pickup_clock' => '07:00', 'kind' => 'table', 'party_size' => 4, 'floor' => 1];
        $this->actingAs($guest)->get(route('reservations.create', $filters))->assertOk()
            ->assertSeeHtml('data-seat="T1-B01" data-availability="held"')
            ->assertSeeHtml('data-seat="T1-B02" data-availability="occupied"')
            ->assertSeeHtml('data-seat="T1-B03" data-availability="free"')->assertDontSee('T2-B01');
        foreach (['T1-B01', 'T1-B02', 'T1-B06'] as $unavailable) {
            $this->postJson(route('reservations.store'), [...$this->input($unavailable, '07:00'), 'party_size' => 4])
                ->assertJsonValidationErrors('reservation');
        }
        $this->actingAs($staff)->post($walkInRoute, ['arriving' => '0'])->assertStatus(303);
        $this->assertSame('free', $walkIn->refresh()->availability());
        $this->assertSame($staff->id, $walkIn->walk_out_actor_id);
        $this->actingAs(User::factory()->create(['role' => UserRole::Admin]))
            ->post(route('operations.reservations.seat-active', $walkIn->id), ['active' => '0'])->assertStatus(303);
        $this->actingAs($guest)->postJson(route('reservations.store'), $this->input('T1-B02', '07:00'))
            ->assertJsonValidationErrors('reservation');
        $this->assertDatabaseCount('reservations', 1);
    }

    public function test_checkout_idempotency_ownership_and_customer_cancellation_release_the_seat(): void
    {
        $this->at('21:30');
        $owner = User::factory()->create();
        $second = User::factory()->create();
        $input = $this->input('T2-PH', '22:30', ['party_size' => 10]);
        $this->actingAs($owner);
        foreach (['22:25', '22:35', '22:31'] as $bad) {
            $this->postJson(route('reservations.store'), [...$input, 'pickup_clock' => $bad])->assertJsonValidationErrors('pickup_time');
        }
        $this->post(route('reservations.store'), $input)->assertStatus(303);
        $book = $owner->reservations()->sole();
        $this->travel(1)->minutes();
        $this->post(route('reservations.store'), $input)->assertRedirectToRoute('reservations.show', $book->code);
        $this->postJson(route('reservations.store'), [...$input, 'party_size' => 9])->assertConflict();
        $this->get(route('reservations.show', $book->code))->assertOk()->assertSee($book->code)->assertSee('10 khách');
        $this->get(route('reservations.index'))->assertOk()->assertSee('T2-PH');
        $this->actingAs($second)->get(route('reservations.show', $book->code))->assertNotFound();
        $this->postJson(route('reservations.cancel', $book->code), ['confirm' => true])->assertNotFound();
        $this->postJson(route('operations.reservations.check-in', $book->code))->assertForbidden();
        $this->actingAs($owner)->post(route('reservations.cancel', $book->code), ['confirm' => '1'])->assertStatus(303);
        $this->assertSame(ReservationStatus::Cancelled, $book->refresh()->status);
        $this->assertNull($book->active_seat_id);
        $this->at('21:30');
        $replacement = $this->book($second, 'T2-PH', '22:30');
        $this->assertNotSame($book->code, $replacement->code);
        $this->assertCount(2, $book->events);
    }

    public function test_admin_hours_are_bounded_and_existing_booking_keeps_its_check_in_window(): void
    {
        $this->at('18:00');
        $guest = User::factory()->create();
        $existing = $this->book($guest, 'T1-B01', '22:30');
        $change = route('operations.reservations.hours');
        $hours = ['first_slot' => '07:30', 'last_slot' => '22:00'];
        $staff = User::factory()->create(['role' => UserRole::Staff]);
        $this->actingAs($staff)->postJson($change, $hours)->assertForbidden();
        $this->actingAs(User::factory()->create(['role' => UserRole::Admin]))
            ->postJson($change, [...$hours, 'last_slot' => '22:35'])->assertJsonValidationErrors('reservation');
        $this->post($change, $hours)->assertStatus(303);
        $this->actingAs($guest)->get(route('reservations.create'))->assertOk()
            ->assertSeeHtml('value="07:30"')->assertDontSeeHtml('value="22:30"');
        $this->postJson(route('reservations.store'), $this->input('T1-B02', '22:30'))->assertJsonValidationErrors('pickup_time');
        $this->at('22:30');
        $this->actingAs($staff)->post(route('operations.reservations.check-in', $existing->code))->assertStatus(303);
    }

    public static function arrivals(): array
    {
        return [['22:35', 0], ['22:36', 2000], ['22:37', 4000]];
    }

    #[DataProvider('arrivals')]
    public function test_reschedule_check_in_and_release_preserve_fees_and_history(string $arrival, int $lateFee): void
    {
        $this->at('19:00');
        $owner = User::factory()->create();
        $booking = $this->book($owner, 'T1-B01', '21:30');
        $change = route('reservations.reschedule', $booking->code);
        $data = ['pickup_date' => '2026-11-21', 'pickup_clock' => '22:30', 'revision' => 0];
        foreach (['20:25', '22:35', '22:31'] as $bad) {
            $this->postJson($change, [...$data, 'pickup_clock' => $bad])->assertJsonValidationErrors('pickup_time');
        }
        $this->assertSame('21:30', $booking->refresh()->scheduled_at->setTimezone(BookingRules::TIMEZONE)->format('H:i'));
        $this->post($change, $data)->assertStatus(303);
        $this->postJson($change, $data)->assertJsonValidationErrors('reservation');
        $this->assertSame($booking->reservation_seat_id, $booking->refresh()->active_seat_id);
        $this->assertSame(10000, $booking->fees()->sole()->amount_vnd);
        $staff = User::factory()->create(['role' => UserRole::Staff]);
        $checkIn = route('operations.reservations.check-in', $booking->code);
        $this->actingAs($staff)->postJson($checkIn)->assertJsonValidationErrors('reservation');
        $this->post(route('operations.reservations.lookup'), ['code' => strtolower($booking->code)])
            ->assertRedirectToRoute('operations.reservations.show', $booking->code);
        $this->get(route('operations.reservations.index', ['date' => '2026-11-21', 'floor' => 1, 'kind' => 'table', 'status' => 'confirmed']))
            ->assertOk()->assertSee('T1-B01');
        $this->at($arrival);
        $this->post($checkIn)->assertStatus(303);
        $this->postJson($checkIn)->assertJsonValidationErrors('reservation');
        $this->at('23:00');
        $this->artisan('bookings:expire')->expectsOutput('Số lượt đặt chỗ đã hủy do không đến: 0')->assertSuccessful();
        $this->assertSame('occupied', $this->seat('T1-B01')->availability());
        $this->get(route('operations.reservations.show', $booking->code))->assertOk()
            ->assertViewHas('counterTotal', 10000 + $lateFee);
        $collect = route('operations.reservations.collect', $booking->code);
        $this->postJson($collect, ['amount_vnd' => 1])->assertJsonValidationErrors('amount_vnd');
        $this->post($collect, ['amount_vnd' => 10000 + $lateFee])->assertStatus(303);
        $this->postJson($collect, ['amount_vnd' => 10000 + $lateFee])->assertJsonValidationErrors('amount_vnd');
        $this->post(route('operations.reservations.release', $booking->code))->assertStatus(303);
        $this->assertSame('free', $this->seat('T1-B01')->availability());
        $this->assertSame(['confirmed', 'checked_in', 'completed'], $booking->events()->orderBy('id')->pluck('to_status')->all());
        $this->assertSame($staff->id, $booking->events()->where('to_status', 'completed')->sole()->actor_id);
    }

    public function test_no_show_boundary_and_shared_uc26_streak_follow_event_time(): void
    {
        $this->at('19:00');
        $customer = User::factory()->create();
        $res = $this->book($customer, 'T1-B01', '22:25');
        $order = $customer->orders()->create([
            'order_number' => 'CF-'.Str::ulid(), 'checkout_key' => Str::uuid()->toString(), 'request_hash' => str_repeat('a', 64),
            'recipient_name' => 'Khách thử', 'phone' => '0901234567', 'fulfillment_type' => 'pickup',
            'status' => 'pending', 'subtotal_vnd' => 30000, 'discount_vnd' => 0, 'delivery_fee_vnd' => 0, 'total_vnd' => 30000,
        ]);
        $order->payment()->create(['method' => 'cod', 'status' => 'pending', 'amount_vnd' => 30000]);
        $order->scheduledPickup()->create([
            'code' => strtoupper(bin2hex(random_bytes(8))), 'scheduled_at' => $this->viet('22:30'), 'arrival_deadline' => $this->viet('23:00'),
        ]);
        $closingCustomer = User::factory()->create();
        $closing = $this->book($closingCustomer, 'T1-B02', '22:30');
        $staff = User::factory()->create(['role' => UserRole::Staff]);
        $this->at('22:59');
        $this->actingAs($staff)->get(route('operations.reservations.show', $closing->code))->assertOk()->assertSee('Ghi nhận khách đến');
        $this->assertSame('held', $this->seat('T1-B02')->availability());
        $this->at('23:00');
        $this->postJson(route('operations.reservations.check-in', $closing->code))->assertJsonValidationErrors('reservation');
        $this->artisan('bookings:expire')->expectsOutput('Số đơn hẹn đã hủy do không đến: 1')
            ->expectsOutput('Số lượt đặt chỗ đã hủy do không đến: 2')->assertSuccessful();
        $this->artisan('bookings:expire')->expectsOutput('Số lượt đặt chỗ đã hủy do không đến: 0')->assertSuccessful();
        $this->assertSame(ReservationStatus::NoShow, $res->refresh()->status);
        $this->assertEquals($this->viet('23:00'), $closing->refresh()->no_show_at);
        $this->assertSame('free', $this->seat('T1-B02')->availability());
        $this->assertSame(2, $closing->events()->count());
        $this->assertEquals($this->viet('23:00')->addDays(7), $customer->refresh()->booking_blocked_until);
        $this->actingAs($customer)->postJson(route('reservations.store'), $this->input('T1-B01', '07:00', ['pickup_date' => '2026-11-22']))
            ->assertJsonValidationErrors('pickup_time');
        $category = Category::create(['name' => 'Cà phê', 'slug' => 'test-reservation']);
        $product = $category->products()->create(['name' => 'Cà phê', 'slug' => 'product-reservation']);
        $size = $product->sizes()->create(['label' => 'Vừa', 'code' => 'm', 'price_vnd' => 40000]);
        app(ShoppingCart::class)->add($customer, $product->id, ['size_id' => $size->id], 1);
        $basket = ['cart_version' => $customer->cart()->sole()->version, 'method' => 'cod', 'fulfillment_type' => 'pickup',
            'recipient_name' => 'Khách thử', 'phone' => '0901234567'];
        $this->postJson(route('checkout.review'), [...$basket, 'pickup_time' => '2026-11-22 07:00'])->assertJsonValidationErrors('pickup_time');
        $this->post(route('checkout.review'), $basket)->assertOk();
        $this->travel(7)->days();
        $this->actingAs($customer->refresh())->post(route('reservations.store'), $this->input('T1-B01', '07:00', ['pickup_date' => '2026-11-29']))
            ->assertStatus(303);
        $new = $customer->reservations()->latest('id')->firstOrFail();
        $this->travelTo($this->viet('07:00')->addDays(8));
        $this->actingAs($staff)->post(route('operations.reservations.check-in', $new->code))->assertStatus(303);
        $this->assertSame(0, $customer->refresh()->booking_no_shows);
    }

    public function test_bank_confirmation_and_fallback_keep_only_unpaid_counter_fees_on_the_invoice(): void
    {
        config(['checkout.bank_transfer' => ['bank_name' => 'Ngân hàng thử', 'account_number' => '1234', 'account_holder' => 'Quán thử']]);
        $this->at('19:00');
        $guest = User::factory()->create();
        $booking = $this->book($guest, 'T2-PH', '21:30', ['channel' => 'app']);
        $this->post(route('reservations.reschedule', $booking->code), ['pickup_date' => '2026-11-21', 'pickup_clock' => '22:30', 'revision' => 0])
            ->assertStatus(303);
        $change = $booking->fees()->sole();
        $this->get(route('reservations.show', $booking->code))->assertOk()->assertSee($change->transfer_code);
        config(['checkout.bank_transfer' => []]);
        $this->at('22:37');
        $this->actingAs(User::factory()->create(['role' => UserRole::Staff]))
            ->post(route('operations.reservations.check-in', $booking->code))->assertStatus(303);
        $late = $booking->fees()->where('kind', 'late')->sole();
        $this->assertSame('1234', $late->bank_details['account_number']);
        $confirm = route('operations.reservations.resolve', [$booking->code, $change->id]);
        $fallback = route('operations.reservations.counter', [$booking->code, $late->id]);
        $details = ['result' => 'paid', 'amount_vnd' => 10000, 'memo' => $change->transfer_code, 'reference' => 'BANK-UC27'];
        $this->postJson($confirm, [...$details, 'amount_vnd' => 1])->assertJsonValidationErrors('fee');
        $this->post($confirm, $details)->assertStatus(303);
        $this->post($confirm, $details)->assertStatus(303);
        $this->postJson($fallback)->assertJsonValidationErrors('fee');
        $this->at('23:00');
        $this->post(route('operations.reservations.resolve', [$booking->code, $late->id]), ['result' => 'expired'])->assertStatus(303);
        $this->post($fallback)->assertStatus(303);
        $this->postJson($fallback)->assertJsonValidationErrors('fee');
        $this->get(route('operations.reservations.show', $booking->code))->assertOk()
            ->assertSee('Phí đổi lịch')->assertSee('Phí đến trễ')->assertSee('Qua ứng dụng')->assertSee('Tại quầy')
            ->assertSee('Đã thanh toán')->assertSee('Chờ thanh toán')->assertViewHas('counterTotal', 4000);
    }

    private function at(string $time): void
    {
        $this->travelTo($this->viet($time));
    }

    private function viet(string $time): CarbonImmutable
    {
        return CarbonImmutable::parse('2026-11-21 '.$time, BookingRules::TIMEZONE)->utc();
    }

    private function seat(string $code): ReservationSeat
    {
        return ReservationSeat::query()->where('code', $code)->sole();
    }

    private function input(string $code, string $time, array $overrides = []): array
    {
        $seat = $this->seat($code);

        return [
            'seat_id' => $seat->id, 'kind' => $seat->kind, 'party_size' => 2, 'pickup_date' => '2026-11-21',
            'pickup_clock' => $time, 'contact_name' => 'Khách thử', 'phone' => '0901234567',
            'channel' => 'counter', 'booking_key' => Str::uuid()->toString(), ...$overrides,
        ];
    }

    private function book(User $customer, string $code, string $time, array $overrides = []): Reservation
    {
        $data = $this->input($code, $time, $overrides);
        $this->actingAs($customer)->post(route('reservations.store'), $data)->assertStatus(303);

        return $customer->reservations()->where('booking_key', $data['booking_key'])->sole();
    }
}
