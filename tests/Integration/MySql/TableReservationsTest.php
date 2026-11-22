<?php

namespace Tests\Integration\MySql;

use App\Enums\ReservationStatus;
use App\Enums\UserRole;
use App\Models\Reservation;
use App\Models\ReservationSeat;
use App\Models\User;
use App\Services\BookingExpirations;
use App\Services\BookingRules;
use App\Services\ReservationCalendar;
use App\Services\TableReservations;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class TableReservationsTest extends MySqlTestCase
{
    public function test_mysql_rejects_stale_seat_choices_and_reuses_released_seats(): void
    {
        DB::setDefaultConnection($this->primary->getName());
        $this->travelTo(CarbonImmutable::parse('2026-11-21 20:00', BookingRules::TIMEZONE)->utc());
        $this->primary->beginTransaction();

        try {
            $first = User::factory()->create();
            $second = User::factory()->create();
            $staff = User::factory()->create(['role' => UserRole::Staff]);
            $seat = ReservationSeat::query()->where('code', 'T1-B01')->sole();
            $service = app(TableReservations::class);
            $input = [
                'seat_id' => $seat->id, 'kind' => 'table', 'party_size' => 4,
                'pickup_time' => '2026-11-21 21:00', 'contact_name' => 'Khách thử',
                'phone' => '0901234567', 'channel' => 'counter', 'booking_key' => Str::uuid()->toString(),
            ];
            $booking = $service->place($first, $input);
            $service->reschedule($first, $booking->code, '2026-11-21 22:00', 0);
            $this->assertSame($seat->id, $booking->refresh()->active_seat_id);
            $this->assertSame(10000, $booking->fees()->sole()->amount_vnd);

            try {
                $service->place($second, [...$input, 'booking_key' => Str::uuid()->toString()]);
                $this->fail('A held seat must not accept another booking.');
            } catch (ValidationException $e) {
                $this->assertArrayHasKey('reservation', $e->errors());
            }

            $this->travel(2)->hours();
            $service->checkIn($staff, $booking->code);
            $this->travel(1)->hour();
            $this->assertSame(0, app(BookingExpirations::class)->run()['reservations']);
            $service->release($staff, $booking->code);
            $next = $service->place($second, [...$input, 'pickup_time' => '2026-11-22 07:00', 'booking_key' => Str::uuid()->toString()]);
            $this->assertSame(ReservationStatus::Completed, $booking->refresh()->status);
            $this->assertSame(0, Reservation::query()->where('active_seat_id', $seat->id)->count());
            $this->assertNull($next->active_seat_id);
            $this->assertSame('free', $seat->fresh()->availability());
            $this->assertFalse(ReservationCalendar::available($seat->fresh(), $next->scheduled_at));
            try {
                $service->place($first, [...$input, 'pickup_time' => '2026-11-22 08:00', 'booking_key' => Str::uuid()->toString()]);
                $this->fail('A future reservation must reject another booking on the same date without occupying today.');
            } catch (ValidationException $e) {
                $this->assertArrayHasKey('reservation', $e->errors());
            }
            $this->travelTo(CarbonImmutable::parse('2026-11-22 07:00', BookingRules::TIMEZONE)->utc());
            $this->assertSame('held', $seat->fresh()->availability());
            $service->checkIn($staff, $next->code);
            $this->assertSame(1, Reservation::query()->where('active_seat_id', $seat->id)->count());
            $this->assertSame($seat->id, $next->refresh()->active_seat_id);
        } finally {
            $this->primary->rollBack();
        }
    }
}
