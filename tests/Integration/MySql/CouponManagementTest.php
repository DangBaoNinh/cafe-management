<?php

namespace Tests\Integration\MySql;

use App\Enums\UserRole;
use App\Models\Coupon;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CouponManagementTest extends MySqlTestCase
{
    public function test_mysql_stores_utc_and_enforces_unique_coupon_codes(): void
    {
        DB::setDefaultConnection($this->primary->getName());
        $this->primary->beginTransaction();

        try {
            $code = Str::random(14);
            $this->actingAs(User::factory()->create(['role' => UserRole::Admin]));
            $this->post(route('admin.coupons.store'), [
                'code' => strtolower($code),
                'discount_type' => 'fixed',
                'discount_value' => (string) PHP_INT_MAX,
                'min_subtotal_vnd' => '0',
                'starts_at' => '2040-11-22T03:00',
                'ends_at' => '2040-11-22T04:00',
                'is_active' => '1',
            ])->assertRedirectToRoute('admin.coupons.index');

            $coupon = Coupon::query()->where('code', strtoupper($code))->sole();
            $this->assertSame(strtoupper($code), $coupon->code);
            $this->assertSame('2040-11-21 20:00:00', $coupon->getRawOriginal('starts_at'));
            $this->assertSame(PHP_INT_MAX, $coupon->discount_value);
            $this->assertSame(50001, $coupon->discountFor(50001, 0, 0, CarbonImmutable::parse('2040-11-21 20:00:00 UTC')));
            $this->assertNull($coupon->discountFor(50001, 0, 0, CarbonImmutable::parse('2040-11-21 21:00:00 UTC')));

            $duplicate = $coupon->replicate();
            $duplicate->code = strtolower($code);
            $this->expectException(UniqueConstraintViolationException::class);
            $duplicate->save();
        } finally {
            $this->primary->rollBack();
        }
    }
}
