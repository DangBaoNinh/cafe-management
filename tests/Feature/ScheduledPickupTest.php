<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Category;
use App\Models\Order;
use App\Models\ScheduledPickup;
use App\Models\User;
use App\Services\BookingRules;
use App\Services\ShoppingCart;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ScheduledPickupTest extends TestCase
{
    use RefreshDatabase;

    public function test_checkout_enforces_slots_preserves_cart_and_reuses_the_original_ticket(): void
    {
        $this->at('06:00');
        $customer = User::factory()->create();
        $details = $this->basket($customer);
        $this->actingAs($customer)->get(route('checkout.create'))->assertOk()
            ->assertSeeHtml('value="07:00"')->assertSeeHtml('value="22:30"')
            ->assertDontSeeHtml('value="22:35"')->assertDontSeeHtml('value="07:01"');
        foreach (['06:55', '07:01', '22:35'] as $time) {
            $this->postJson(route('checkout.review'), [...$details, 'pickup_time' => "2026-11-21 $time"])
                ->assertUnprocessable()->assertJsonValidationErrors('pickup_time');
        }
        $this->postJson(route('checkout.review'), [...$details, 'fulfillment_type' => 'delivery', 'address_id' => 1, 'pickup_time' => '2026-11-21 07:00'])
            ->assertUnprocessable()->assertJsonValidationErrors('pickup_time');
        $input = $this->review([...$details, 'pickup_mode' => 'scheduled', 'pickup_date' => '2026-11-21', 'pickup_clock' => '07:00']);
        $this->travel(1)->minutes();
        $this->postJson(route('checkout.store'), $input)->assertUnprocessable()->assertJsonValidationErrors('pickup_time');
        $this->assertSame(1, $customer->cart()->sole()->items()->count());
        $this->assertDatabaseCount('orders', 0);
        $this->at('06:00');
        $this->post(route('checkout.store'), $input)->assertStatus(303);
        $order = $customer->orders()->sole();
        $pickup = $order->scheduledPickup()->sole();
        $this->assertSame('00:00', $pickup->scheduled_at->format('H:i'));
        $this->assertSame(0, $order->delivery_fee_vnd);
        $this->travel(1)->hour();
        $this->post(route('checkout.store'), $input)->assertRedirectToRoute('checkout.confirmation', $order->order_number);
        $this->postJson(route('checkout.store'), [...$input, 'pickup_time' => '2026-11-21 22:30'])
            ->assertConflict()->assertJsonValidationErrors('checkout_key');
        $this->assertDatabaseCount('scheduled_pickups', 1);
        $this->get(route('orders.show', $order->order_number))->assertOk()->assertSee($pickup->code);
        $other = User::factory()->create();
        $this->actingAs($other)->get(route('orders.show', $order->order_number))->assertNotFound();
        $this->get(route('operations.pickups.show', $pickup->code))->assertForbidden();
        $this->actingAs(User::factory()->create(['role' => UserRole::Staff, 'is_active' => false]))
            ->postJson(route('operations.pickups.arrive', $pickup->code), ['channel' => 'counter'])->assertForbidden();
    }

    public function test_closing_slot_has_no_capacity_limit_and_location_filters_use_vietnam_time(): void
    {
        $this->at('21:30');
        for ($i = 0; $i < 2; $i++) {
            $customer = User::factory()->create();
            $this->actingAs($customer);
            $this->post(route('checkout.store'), $this->review([...$this->basket($customer), 'pickup_time' => '2026-11-21 22:30']))
                ->assertStatus(303);
        }
        $this->assertDatabaseCount('scheduled_pickups', 2);
        $this->actingAs(User::factory()->create(['role' => UserRole::Admin]));
        $this->get(route('operations.pickups.index', ['date' => '2026-11-21', 'time' => '22:30']))
            ->assertOk()->assertViewHas('pickups', fn ($pickups) => $pickups->total() === 2);
        $this->get(route('operations.pickups.index', ['date' => '2026-11-21', 'time' => '22:25']))
            ->assertOk()->assertViewHas('pickups', fn ($pickups) => $pickups->total() === 0);
        $this->postJson(route('operations.pickups.lookup'), ['code' => '0000000000000000'])->assertJsonValidationErrors('code');
    }

    public function test_reschedule_checks_the_recent_revision_and_uses_the_same_ticket(): void
    {
        $this->at('19:00');
        [$customer, $order, $pickup] = $this->scheduled('21:30');
        $action = route('orders.reschedule-pickup', $order->order_number);
        $this->actingAs($customer);
        $input = ['pickup_date' => '2026-11-21', 'pickup_clock' => '22:30', 'revision' => 0, 'channel' => 'counter'];
        foreach (['20:25', '22:35', '22:01'] as $time) {
            $this->postJson($action, [...$input, 'pickup_clock' => $time])->assertJsonValidationErrors('pickup_time');
        }
        $this->postJson($action, [...$input, 'pickup_clock' => '19:55'])->assertJsonValidationErrors('pickup_time');
        $this->assertSame('14:30', $pickup->refresh()->scheduled_at->format('H:i'));
        $this->assertDatabaseCount('pickup_fees', 0);
        $this->post($action, $input)->assertStatus(303);
        $this->postJson($action, $input)->assertJsonValidationErrors('pickup_time');
        $this->assertSame('15:30', $pickup->refresh()->scheduled_at->format('H:i'));
        $this->assertSame(10000, $pickup->fees()->sole()->amount_vnd);
        $this->get(route('orders.show', $order->order_number))->assertOk()->assertSee($pickup->code)->assertSee('22:30');
        $this->at('22:30');
        $this->postJson($action, [...$input, 'revision' => 1])->assertJsonValidationErrors('pickup_time');
    }

    public static function lateFees(): array
    {
        return [['22:35', 0], ['22:36', 2000], ['22:37', 4000]];
    }

    #[DataProvider('lateFees')]
    public function test_latest_arrival_charge_is_frozen_and_handoff_is_recorded_once(string $arrival, int $fee): void
    {
        [, $order, $pickup] = $this->scheduled('22:30', 'preparing');
        $this->at($arrival);
        $staff = User::factory()->create(['role' => UserRole::Staff]);
        $handoff = route('operations.pickups.handoff', $pickup->code);
        $this->actingAs($staff)->post(route('operations.pickups.lookup'), ['code' => strtolower($pickup->code)])
            ->assertRedirectToRoute('operations.pickups.show', $pickup->code);
        $this->post(route('operations.pickups.arrive', $pickup->code), ['channel' => 'counter'])->assertStatus(303);
        $this->postJson($handoff, ['collected_vnd' => 40000 + $fee])->assertJsonValidationErrors('pickup_time');
        $this->at('23:15');
        $this->artisan('pickups:expire')->assertSuccessful();
        $this->post(route('operations.pickups.arrive', $pickup->code), ['channel' => 'counter'])->assertStatus(303);
        $this->assertSame($fee, (int) $pickup->fees()->sum('amount_vnd'));
        $this->post(route('operations.orders.prepare', $order->order_number), ['expected_status' => 'preparing', 'target_status' => 'ready'])
            ->assertStatus(303);
        $this->postJson(route('operations.orders.handoff', $order->order_number), [
            'expected_status' => 'ready', 'target_status' => 'completed', 'fulfillment_type' => 'pickup',
            'method' => 'cod', 'confirmed' => '1', 'cod_amount_vnd' => '40000',
        ])->assertConflict();
        $this->postJson($handoff, ['collected_vnd' => 41000 + $fee])->assertJsonValidationErrors('pickup_time');
        $this->get(route('operations.pickups.show', $pickup->code))->assertOk()
            ->assertViewHas('invoice', fn ($invoice) => $invoice['total'] === 40000 + $fee);
        $this->post($handoff, ['collected_vnd' => 40000 + $fee])->assertStatus(303);
        $this->postJson($handoff, ['collected_vnd' => 40000 + $fee])->assertJsonValidationErrors('pickup_time');
        $this->assertNotNull($pickup->refresh()->used_at);
        $this->assertSame('completed', $order->refresh()->status->value);
        $this->assertSame(1, $order->statusHistories()->where('to_status', 'completed')->count());
        $this->assertSame('paid', $order->payment()->sole()->status->value);
        $this->assertSame($fee > 0 ? 1 : 0, $pickup->fees()->where('status', 'paid')->count());
    }

    public function test_online_fees_require_a_matching_transfer_or_a_terminal_result_before_fallback(): void
    {
        config(['checkout.bank_transfer' => ['bank_name' => 'Ngân hàng thử', 'account_number' => '1234', 'account_holder' => 'Quán thử']]);
        $this->at('19:00');
        [$customer, $order, $pickup] = $this->scheduled('21:30', 'ready');
        $this->actingAs($customer)->post(route('orders.reschedule-pickup', $order->order_number), [
            'pickup_date' => '2026-11-21', 'pickup_clock' => '22:30', 'revision' => 0, 'channel' => 'app',
        ])->assertStatus(303);
        $fee = $pickup->fees()->sole();
        $this->get(route('orders.show', $order->order_number))->assertOk()->assertSee($fee->transfer_code);
        $this->at('22:37');
        $this->actingAs(User::factory()->create(['role' => UserRole::Staff]));
        $this->post(route('operations.pickups.arrive', $pickup->code), ['channel' => 'app'])->assertStatus(303);
        $late = $pickup->fees()->where('kind', 'late')->sole();
        $resolve = route('operations.pickups.resolve', [$pickup->code, $fee->id]);
        $counter = route('operations.pickups.counter', [$pickup->code, $late->id]);
        $handoff = route('operations.pickups.handoff', $pickup->code);
        $this->postJson($handoff, ['collected_vnd' => 40000])->assertJsonValidationErrors('pickup_time');
        $this->postJson($counter)->assertJsonValidationErrors('fee');
        $transfer = ['result' => 'paid', 'amount_vnd' => 10000, 'memo' => $fee->transfer_code, 'reference' => 'BANK-001'];
        $this->postJson($resolve, [...$transfer, 'memo' => 'sai'])->assertJsonValidationErrors('fee');
        $this->postJson($resolve, [...$transfer, 'amount_vnd' => 9999])->assertJsonValidationErrors('fee');
        $this->post($resolve, $transfer)->assertStatus(303);
        $this->post($resolve, $transfer)->assertStatus(303);
        $this->post(route('operations.pickups.resolve', [$pickup->code, $late->id]), ['result' => 'expired'])->assertStatus(303);
        $this->post($counter)->assertStatus(303);
        $this->postJson($counter)->assertJsonValidationErrors('fee');
        $this->get(route('operations.pickups.show', $pickup->code))->assertOk()
            ->assertSee('Phí đổi lịch')->assertSee('Phí đến trễ')->assertSee('Qua ứng dụng')->assertSee('Đã thanh toán')->assertSee('Tại quầy')
            ->assertViewHas('invoice', fn ($invoice) => $invoice['total'] === 44000);
        $this->post($handoff, ['collected_vnd' => 44000])->assertStatus(303);
        $this->assertSame('BANK-001', $fee->refresh()->bank_reference);
        $this->assertSame('expired', $late->refresh()->transfer_result);
        $this->assertSame('paid', $late->status);
    }

    public function test_two_no_shows_expire_at_the_boundary_and_suspend_only_bookings(): void
    {
        $this->at('22:59');
        [$customer, $first, $pickup] = $this->scheduled('22:30', 'pending', paymentStatus: 'paid');
        [, $second] = $this->scheduled('22:30', 'ready', $customer);
        $this->artisan('pickups:expire')->expectsOutput('Số đơn hẹn đã hủy do không đến: 0')->assertSuccessful();
        $this->assertTrue($pickup->isOpen($first));
        $this->at('23:00');
        $this->actingAs($customer)->postJson(route('orders.cancel', $first->order_number), ['expected_status' => 'pending', 'confirm' => '1'])
            ->assertConflict()->assertJsonValidationErrors('expected_status');
        $this->actingAs(User::factory()->create(['role' => UserRole::Staff]))
            ->postJson(route('operations.pickups.arrive', $pickup->code), ['channel' => 'counter'])
            ->assertJsonValidationErrors('pickup_time');
        $this->artisan('pickups:expire')->expectsOutput('Số đơn hẹn đã hủy do không đến: 2')->assertSuccessful();
        $this->artisan('pickups:expire')->expectsOutput('Số đơn hẹn đã hủy do không đến: 0')->assertSuccessful();
        $this->assertFalse($pickup->refresh()->isOpen($first->refresh()));
        $this->assertSame('refund_pending', $first->payment()->sole()->status->value);
        $this->assertSame('voided', $second->payment()->sole()->status->value);
        $this->assertNull($first->statusHistories()->sole()->actor_id);
        $this->assertSame(2, $customer->refresh()->booking_no_shows);
        $this->assertSame('2026-11-28 16:00', $customer->booking_blocked_until->format('Y-m-d H:i'));
        $details = $this->basket($customer);
        $this->actingAs($customer)->postJson(route('checkout.review'), [...$details, 'pickup_time' => '2026-11-22 07:00'])
            ->assertJsonValidationErrors('pickup_time');
        $this->post(route('checkout.store'), $this->review($details))->assertStatus(303);
        $this->assertSame(1, $customer->orders()->whereDoesntHave('scheduledPickup')->count());
        $this->travel(7)->days();
        $this->post(route('checkout.review'), [...$this->basket($customer), 'pickup_time' => '2026-11-29 07:00'])->assertOk();
    }

    public function test_a_transfer_discovered_after_cancellation_is_queued_for_admin_refund(): void
    {
        config(['checkout.bank_transfer' => ['bank_name' => 'Ngân hàng thử', 'account_number' => '1234', 'account_holder' => 'Quán thử']]);
        $this->at('19:00');
        [$customer, $order, $pickup] = $this->scheduled('21:30');
        $this->actingAs($customer)->post(route('orders.reschedule-pickup', $order->order_number), [
            'pickup_date' => '2026-11-21', 'pickup_clock' => '22:30', 'revision' => 0, 'channel' => 'app',
        ])->assertStatus(303);
        $fee = $pickup->fees()->sole();
        $this->post(route('orders.cancel', $order->order_number), ['expected_status' => 'pending', 'confirm' => '1'])->assertStatus(303);
        $this->assertSame('voided', $fee->refresh()->status);
        $this->actingAs(User::factory()->create(['role' => UserRole::Staff]));
        $this->get(route('operations.pickups.show', $pickup->code))->assertOk()->assertSee('Ghi nhận tiền đến để hoàn');
        $this->post(route('operations.pickups.resolve', [$pickup->code, $fee->id]), [
            'result' => 'paid', 'amount_vnd' => 10000, 'memo' => $fee->transfer_code, 'reference' => 'BANK-LATE',
        ])->assertStatus(303);
        $this->assertSame('refund_pending', $fee->refresh()->status);
        $refund = route('operations.pickups.refund', [$pickup->code, $fee->id]);
        $this->postJson($refund, ['refund_reference' => 'REFUND-LATE'])->assertForbidden();
        $this->actingAs(User::factory()->create(['role' => UserRole::Admin]))
            ->post($refund, ['refund_reference' => 'REFUND-LATE'])->assertStatus(303);
        $this->assertSame('refunded', $fee->refresh()->status);
    }

    public function test_shared_attendance_counts_a_prior_booking_and_arrival_resets_the_streak(): void
    {
        $this->at('21:00');
        [$customer, , $missed] = $this->scheduled('21:30');
        // A future table reservation records its outcome through the same user lock and policy.
        DB::transaction(fn () => BookingRules::recordAttendance(
            User::query()->lockForUpdate()->findOrFail($customer->id), false, now(),
        ));
        $this->at('22:00');
        $this->artisan('pickups:expire')->assertSuccessful();
        $this->assertNotNull($missed->refresh()->no_show_at);
        $this->assertSame(2, $customer->refresh()->booking_no_shows);
        $this->assertTrue($customer->booking_blocked_until->isFuture());

        [, , $visited] = $this->scheduled('22:30', customer: $customer);
        $this->actingAs(User::factory()->create(['role' => UserRole::Staff]))
            ->post(route('operations.pickups.arrive', $visited->code), ['channel' => 'counter'])->assertStatus(303);
        $this->assertSame(0, $customer->refresh()->booking_no_shows);
        $this->assertTrue($customer->booking_blocked_until->isFuture());
    }

    public function test_delayed_expiry_does_not_count_a_no_show_before_the_latest_arrival(): void
    {
        $this->at('18:00');
        [$customer, $oldOrder, $oldPickup] = $this->scheduled('20:00');
        [, , $visited] = $this->scheduled('21:00', customer: $customer);
        [, , $newPickup] = $this->scheduled('21:15', customer: $customer);

        $this->at('21:00');
        $this->actingAs(User::factory()->create(['role' => UserRole::Staff]))
            ->post(route('operations.pickups.arrive', $visited->code), ['channel' => 'counter'])->assertStatus(303);
        $this->artisan('pickups:expire')->expectsOutput('Số đơn hẹn đã hủy do không đến: 1')->assertSuccessful();
        $streakAfterDelayedExpiry = $customer->refresh()->booking_no_shows;
        $this->assertSame('cancelled', $oldOrder->refresh()->status->value);
        $this->assertEquals($oldPickup->arrival_deadline, $oldPickup->refresh()->no_show_at);

        $this->at('21:45');
        $this->artisan('pickups:expire')->expectsOutput('Số đơn hẹn đã hủy do không đến: 1')->assertSuccessful();
        $this->assertNotNull($newPickup->refresh()->no_show_at);
        $this->actingAs($customer->refresh())->postJson(route('checkout.review'), [
            ...$this->basket($customer), 'pickup_time' => '2026-11-22 07:00',
        ])->assertOk();
        $this->assertSame(0, $streakAfterDelayedExpiry);
        $this->assertSame(1, $customer->refresh()->booking_no_shows);
        $this->assertNull($customer->booking_blocked_until);
    }

    public function test_cancellation_and_rejection_invalidate_tickets_without_counting_absences(): void
    {
        $this->at('20:00');
        [$customer, $order, $pickup] = $this->scheduled('22:30');
        $this->actingAs($customer)->post(route('orders.cancel', $order->order_number), ['expected_status' => 'pending', 'confirm' => '1'])
            ->assertStatus(303);
        $staff = User::factory()->create(['role' => UserRole::Staff]);
        $this->actingAs($staff)->postJson(route('operations.pickups.arrive', $pickup->code), ['channel' => 'counter'])
            ->assertJsonValidationErrors('pickup_time');
        [, $rejected, $secondPickup] = $this->scheduled('22:30', customer: $customer);
        $this->post(route('operations.orders.decide', $rejected->order_number), ['decision' => 'rejected', 'expected_status' => 'pending', 'reason' => 'Hết nguyên liệu'])
            ->assertStatus(303);
        $this->get(route('operations.pickups.show', $secondPickup->code))->assertOk()->assertSee('Hết hiệu lực');
        $this->at('23:00');
        $this->artisan('pickups:expire')->expectsOutput('Số đơn hẹn đã hủy do không đến: 0')->assertSuccessful();
        $this->assertSame(0, $customer->refresh()->booking_no_shows);
    }

    private function at(string $time): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-21 '.$time, BookingRules::TIMEZONE)->utc());
    }

    private function basket(User $customer): array
    {
        $category = Category::create(['name' => 'Cà phê', 'slug' => fake()->uuid()]);
        $product = $category->products()->create(['name' => 'Cà phê sữa', 'slug' => fake()->uuid()]);
        $size = $product->sizes()->create(['label' => 'Vừa', 'code' => 'm', 'price_vnd' => 40000]);
        app(ShoppingCart::class)->add($customer, $product->id, ['size_id' => $size->id], 1);

        return [
            'cart_version' => $customer->cart()->sole()->version, 'fulfillment_type' => 'pickup', 'method' => 'cod',
            'recipient_name' => 'Khách nhận', 'phone' => '0901234567',
        ];
    }

    private function review(array $details): array
    {
        $response = $this->post(route('checkout.review'), $details)->assertOk();

        return [
            ...array_filter($response->viewData('details')->fields(), fn ($value) => $value !== null),
            'accepted_total_vnd' => $response->viewData('quote')['order']['total_vnd'],
            'quote_hash' => $response->viewData('quote')['hash'], 'checkout_key' => $response->viewData('key'),
        ];
    }

    /** @return array{User, Order, ScheduledPickup} */
    private function scheduled(string $time, string $status = 'pending', ?User $customer = null, string $paymentStatus = 'pending'): array
    {
        $customer ??= User::factory()->create();
        $key = fake()->uuid();
        $order = $customer->orders()->create([
            'order_number' => 'CF-'.Str::ulid(), 'checkout_key' => $key, 'request_hash' => hash('sha256', $key),
            'recipient_name' => 'Khách nhận', 'phone' => '0901234567', 'fulfillment_type' => 'pickup', 'status' => $status,
            'subtotal_vnd' => 40000, 'discount_vnd' => 0, 'delivery_fee_vnd' => 0, 'total_vnd' => 40000,
        ]);
        $order->payment()->create(['method' => $paymentStatus === 'paid' ? 'bank_transfer' : 'cod', 'status' => $paymentStatus, 'amount_vnd' => 40000]);
        $slot = CarbonImmutable::parse('2026-11-21 '.$time, BookingRules::TIMEZONE)->utc();
        $pickup = $order->scheduledPickup()->create([
            'code' => strtoupper(bin2hex(random_bytes(8))), 'scheduled_at' => $slot, 'arrival_deadline' => $slot->addMinutes(30),
        ]);

        return [$customer, $order, $pickup];
    }
}
