<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Reservation;
use App\Models\ReservationSeat;
use App\Models\User;
use App\Services\TableSessions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class PublicSeatingTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_pages_use_live_components_without_prototype_data(): void
    {
        foreach (['story', 'spaces'] as $route) {
            $this->get(route($route))->assertOk()->assertSee('Toàn')
                ->assertDontSee('assets/theme.js')->assertDontSee('seating-demo.js')->assertDontSee('admin.html');
        }
        $this->get(route('spaces'))->assertSee('data-public-seating', false)->assertSee(route('spaces.snapshot', absolute: false));
        $this->get(route('reservations.create'))->assertRedirect(route('login'));
    }

    public function test_snapshot_maps_real_occupancy_and_exposes_only_public_fields(): void
    {
        $customer = User::factory()->create(['name' => 'Thông tin riêng của khách', 'email' => 'private@example.test']);
        $held = ReservationSeat::query()->where('code', 'T1-B01')->sole();
        Reservation::query()->forceCreate([
            'user_id' => $customer->id, 'reservation_seat_id' => $held->id, 'active_seat_id' => $held->id,
            'code' => 'PRIVATE-BOOKING', 'booking_key' => Str::uuid(), 'request_hash' => str_repeat('a', 64),
            'contact_name' => $customer->name, 'phone' => '0901234567', 'party_size' => 2, 'fee_channel' => 'counter',
            'status' => 'confirmed', 'scheduled_at' => now()->addHours(2), 'arrival_deadline' => now()->addHours(2)->addMinutes(30),
        ]);
        $occupied = ReservationSeat::query()->where('code', 'T1-B02')->sole();
        $occupied->walk_in_at = now();
        $occupied->save();
        $inactive = ReservationSeat::query()->where('code', 'T2-B01')->sole();
        $inactive->is_active = false;
        $inactive->save();
        $response = $this->getJson(route('spaces.snapshot'))->assertOk()
            ->assertJsonCount(2, 'areas')->assertJsonCount(22, 'tables')
            ->assertDontSee($customer->email)->assertDontSee($customer->name)->assertDontSee('0901234567')->assertDontSee('PRIVATE-BOOKING');
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->assertEqualsCanonicalizing(['observed_at', 'areas', 'tables'], array_keys($response->json()));
        $tables = collect($response->json('tables'))->keyBy('code');
        $this->assertSame('reserved', $tables['T1-B01']['status']);
        $this->assertSame('occupied', $tables['T1-B02']['status']);
        $this->assertSame('maintenance', $tables['T2-B01']['status']);
        $this->assertSame('available', $tables['T2-B02']['status']);
        foreach ($tables as $table) {
            $this->assertEqualsCanonicalizing(['id', 'code', 'area_id', 'capacity', 'status'], array_keys($table));
        }
    }

    public function test_open_table_session_is_occupied_and_get_does_not_change_it(): void
    {
        $staff = User::factory()->create(['role' => UserRole::Staff]);
        $seat = ReservationSeat::query()->where('code', 'T1-B01')->sole();
        $session = app(TableSessions::class)->open($staff, $seat);
        $before = $session->fresh()->getRawOriginal();

        $response = $this->getJson(route('spaces.snapshot'))->assertOk()->assertDontSee($session->reference);
        $this->assertSame('occupied', collect($response->json('tables'))->firstWhere('code', $seat->code)['status']);
        $this->assertSame($before, $session->fresh()->getRawOriginal());
    }
}
