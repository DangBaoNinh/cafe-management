<?php

namespace Tests\Feature;

use App\Enums\ReservationStatus;
use App\Enums\UserRole;
use App\Models\Reservation;
use App\Models\ReservationSeat;
use App\Models\User;
use App\Services\BookingRules;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class CustomerReservationHistoryTest extends TestCase
{
    use RefreshDatabase;

    private function booking(User $owner, ReservationStatus $status, string $time = '2026-11-24 12:00'): Reservation
    {
        $slot = CarbonImmutable::parse($time, BookingRules::TIMEZONE)->utc();

        return $owner->reservations()->create([
            'reservation_seat_id' => ReservationSeat::where('code', 'T1-B01')->sole()->id,
            'code' => (string) Str::ulid(), 'booking_key' => (string) Str::uuid(), 'request_hash' => hash('sha256', (string) Str::uuid()),
            'contact_name' => 'Khách đặt bàn', 'phone' => '0901234567', 'party_size' => 2, 'fee_channel' => 'counter',
            'status' => $status, 'scheduled_at' => $slot, 'arrival_deadline' => $slot->addMinutes(30),
        ]);
    }

    public function test_customer_history_separates_finished_bookings_and_never_lists_another_customers_records(): void
    {
        $owner = User::factory()->create();
        foreach (ReservationStatus::cases() as $status) {
            $this->booking($owner, $status);
        }
        $private = $this->booking(User::factory()->create(), ReservationStatus::Completed);
        $all = $this->actingAs($owner)->get(route('reservations.index'))->assertOk()->assertSee('Lịch sử đặt bàn')->assertSee('Xem chi tiết');
        $this->assertSame(6, $all->viewData('reservations')->total());
        $all->assertDontSee($private->code);
        $history = $this->get(route('reservations.index', ['scope' => 'history']))->assertOk();
        $this->assertEqualsCanonicalizing([ReservationStatus::Completed, ReservationStatus::Cancelled, ReservationStatus::NoShow], $history->viewData('reservations')->pluck('status')->all());
        $open = $this->get(route('reservations.index', ['scope' => 'open']))->assertOk();
        $this->assertSame(3, $open->viewData('reservations')->total());
        $this->assertFalse($open->viewData('reservations')->contains(fn ($booking) => in_array($booking->status, [ReservationStatus::Completed, ReservationStatus::Cancelled, ReservationStatus::NoShow], true)));
        $this->get(route('dashboard'))->assertOk()->assertSee('Lịch sử đặt bàn');
    }

    public function test_filters_use_vietnam_dates_and_keep_query_parameters_across_pages(): void
    {
        $owner = User::factory()->create();
        $before = $this->booking($owner, ReservationStatus::Completed, '2026-11-23 23:55');
        $inside = $this->booking($owner, ReservationStatus::Completed, '2026-11-24 00:00');
        $this->booking($owner, ReservationStatus::Cancelled, '2026-11-24 12:00');
        $after = $this->booking($owner, ReservationStatus::Completed, '2026-11-25 00:00');
        $filters = ['scope' => 'history', 'from' => '2026-11-24', 'to' => '2026-11-24', 'status' => 'completed'];
        $response = $this->actingAs($owner)->get(route('reservations.index', $filters))->assertOk()
            ->assertSee($inside->code)->assertDontSee($before->code)->assertDontSee($after->code);
        $this->assertSame(1, $response->viewData('reservations')->total());
        $this->get(route('reservations.index', ['to' => '2026-11-23']))->assertOk()->assertSee($before->code)->assertDontSee($inside->code);
        for ($i = 0; $i < 12; $i++) {
            $this->booking($owner, ReservationStatus::Completed);
        }
        $page = $this->get(route('reservations.index', $filters))->assertOk();
        $this->assertSame(13, $page->viewData('reservations')->total());
        $this->assertStringContainsString('scope=history', $page->viewData('reservations')->nextPageUrl());
        $this->get(route('reservations.index', [...$filters, 'page' => 2]))->assertOk()->assertSee($inside->code);
        $this->getJson(route('reservations.index', ['from' => '2026-11-25', 'to' => '2026-11-24']))->assertUnprocessable()->assertJsonValidationErrors('to');
        $this->getJson(route('reservations.index', ['status' => 'unknown']))->assertUnprocessable();
    }

    public function test_archived_booking_details_remain_accessible_to_owner_with_escaped_history(): void
    {
        $owner = User::factory()->create();
        foreach ([ReservationStatus::Completed, ReservationStatus::Cancelled, ReservationStatus::NoShow] as $status) {
            $booking = $this->booking($owner, $status);
            $reason = '<script>alert("private")</script>';
            $booking->events()->create(['from_status' => 'confirmed', 'to_status' => $status->value, 'reason' => $reason, 'occurred_at' => now()]);
            $this->actingAs($owner)->get(route('reservations.show', $booking->code))->assertOk()
                ->assertSee($booking->code)->assertSee('Lịch sử xử lý đặt bàn')->assertSee($status->label())
                ->assertSee($reason)->assertDontSee($reason, false)->assertDontSee('Xác nhận hủy');
            $this->actingAs(User::factory()->create())->get(route('reservations.show', $booking->code))->assertNotFound();
        }
        $this->actingAs(User::factory()->create(['role' => UserRole::Staff]))->get(route('reservations.index'))->assertForbidden();
        $this->actingAs(User::factory()->create(['is_active' => false]))->get(route('reservations.index'))->assertForbidden();
    }
}
