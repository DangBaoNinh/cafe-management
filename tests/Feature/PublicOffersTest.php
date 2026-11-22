<?php

namespace Tests\Feature;

use App\Enums\CouponDiscountType;
use App\Models\Coupon;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicOffersTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_see_only_currently_active_offers(): void
    {
        $now = CarbonImmutable::parse('2026-11-23 08:00:00', Coupon::LOCAL_TIMEZONE);
        $this->travelTo($now);

        $this->coupon('WELCOME-15', true, $now->subHour(), $now->addHour());
        $this->coupon('DISABLED-15', false, $now->subHour(), $now->addHour());
        $this->coupon('FUTURE-15', true, $now->addMinute(), $now->addHour());
        $this->coupon('EXPIRED-15', true, $now->subHours(2), $now->subMinute());

        $this->get(route('offers.index'))
            ->assertOk()
            ->assertSee('Ưu đãi trong ngày')
            ->assertSee('WELCOME-15')
            ->assertDontSee('DISABLED-15')
            ->assertDontSee('FUTURE-15')
            ->assertDontSee('EXPIRED-15');
    }

    private function coupon(string $code, bool $active, CarbonImmutable $startsAt, CarbonImmutable $endsAt): Coupon
    {
        return Coupon::query()->create([
            'code' => $code,
            'discount_type' => CouponDiscountType::Percentage,
            'discount_value' => 15,
            'min_subtotal_vnd' => 50000,
            'max_discount_vnd' => 20000,
            // Coupon timestamps are persisted as UTC by the admin form; keep the
            // fixture on the same storage convention as production data.
            'starts_at' => $startsAt->utc(),
            'ends_at' => $endsAt->utc(),
            'is_active' => $active,
        ]);
    }
}
