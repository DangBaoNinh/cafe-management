<?php

namespace Tests\Feature\Admin;

use App\Enums\CouponDiscountType;
use App\Enums\UserRole;
use App\Models\Coupon;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CouponManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse('2026-11-21 00:00:00 UTC'));
    }

    public function test_admin_creates_edits_and_disables_a_coupon(): void
    {
        $this->actingAs(User::factory()->create(['role' => UserRole::Admin]));
        $this->get(route('admin.coupons.create'))->assertOk()->assertSee('Thêm mã giảm giá');
        $this->post(route('admin.coupons.store'), $this->payload())
            ->assertRedirectToRoute('admin.coupons.index');

        $coupon = Coupon::query()->sole();
        $this->assertSame('CA-PHE_10', $coupon->code);
        $this->assertSame(CouponDiscountType::Percentage, $coupon->discount_type);
        $this->assertSame(10, $coupon->discount_value);
        $this->assertSame(50001, $coupon->min_subtotal_vnd);
        $this->assertSame(8000, $coupon->max_discount_vnd);
        $this->assertSame(20, $coupon->total_limit);
        $this->assertSame(2, $coupon->per_user_limit);
        $this->assertSame('2026-11-21 18:30:00', $coupon->getRawOriginal('starts_at'));
        $this->assertSame('2026-11-21 19:30:00', $coupon->getRawOriginal('ends_at'));
        $this->get(route('admin.coupons.edit', $coupon))->assertOk()
            ->assertSeeHtml('value="2026-11-22T01:30"');

        $this->travelTo(CarbonImmutable::parse('2026-11-21 18:30:00 UTC'));
        $this->get(route('admin.coupons.index'))->assertOk()->assertSee('Đang hiệu lực');
        $this->patch(route('admin.coupons.update', $coupon), $this->payload([
            'discount_type' => 'fixed', 'discount_value' => '25000', 'is_active' => '0',
            'max_discount_vnd' => '', 'total_limit' => '', 'per_user_limit' => '',
        ]))->assertRedirectToRoute('admin.coupons.index');

        $coupon->refresh();
        $this->assertSame(CouponDiscountType::Fixed, $coupon->discount_type);
        $this->assertSame(25000, $coupon->discount_value);
        $this->assertFalse($coupon->is_active);
        $this->assertNull($coupon->max_discount_vnd);
        $this->assertNull($coupon->total_limit);
        $this->assertNull($coupon->per_user_limit);
        $this->get(route('admin.coupons.index'))->assertSee('Đã tắt')->assertSee('25.000 ₫');
    }

    public function test_duplicates_are_rejected_for_creating_and_editing(): void
    {
        $this->actingAs(User::factory()->create(['role' => UserRole::Admin]));
        $this->post(route('admin.coupons.store'), $this->payload())->assertRedirect();
        $this->postJson(route('admin.coupons.store'), $this->payload(['code' => 'Ca-PhE_10']))
            ->assertUnprocessable()->assertJsonValidationErrors('code');
        $this->post(route('admin.coupons.store'), $this->payload(['code' => 'khac']))->assertRedirect();
        $other = Coupon::query()->where('code', 'KHAC')->sole();
        $this->patchJson(route('admin.coupons.update', $other), $this->payload(['code' => ' ca-phe_10 ']))
            ->assertUnprocessable()->assertJsonValidationErrors('code');
        $this->assertSame('KHAC', $other->refresh()->code);
        $this->assertDatabaseCount('coupons', 2);
    }

    public function test_invalid_ranges_and_types_are_rejected(): void
    {
        $this->actingAs(User::factory()->create(['role' => UserRole::Admin]));
        foreach ([
            [['discount_value' => 0], 'discount_value'],
            [['discount_value' => 101], 'discount_value'],
            [['discount_value' => true], 'discount_value'],
            [['discount_value' => '1.5'], 'discount_value'],
            [['discount_type' => 'unknown'], 'discount_type'],
            [['discount_type' => 'fixed'], 'max_discount_vnd'],
            [['max_discount_vnd' => -1], 'max_discount_vnd'],
            [['min_subtotal_vnd' => -1], 'min_subtotal_vnd'],
            [['total_limit' => 0], 'total_limit'],
            [['per_user_limit' => -1], 'per_user_limit'],
            [['starts_at' => '2026-02-30T01:30'], 'starts_at'],
            [['ends_at' => '2026-11-22T01:30'], 'ends_at'],
            [['ends_at' => '2026-11-21T02:30'], 'ends_at'],
            [['code' => 'mã giảm'], 'code'],
        ] as [$values, $error]) {
            $this->postJson(route('admin.coupons.store'), $this->payload($values))
                ->assertUnprocessable()->assertJsonValidationErrors($error);
        }

        $this->assertDatabaseCount('coupons', 0);
    }

    public function test_only_active_admin_can_manage_coupons(): void
    {
        $index = route('admin.coupons.index');
        $store = route('admin.coupons.store');
        $this->get($index)->assertRedirectToRoute('login');
        $this->post($store, $this->payload())->assertRedirectToRoute('login');

        $this->actingAs(User::factory()->create(['role' => UserRole::Admin]));
        $this->post($store, $this->payload())->assertRedirectToRoute('admin.coupons.index');
        $coupon = Coupon::query()->sole();

        foreach ([
            ['role' => UserRole::Customer],
            ['role' => UserRole::Staff],
            ['role' => UserRole::Admin, 'is_active' => false],
        ] as $attributes) {
            $this->actingAs(User::factory()->create($attributes));
            $this->get($index)->assertForbidden();
            $this->get(route('admin.coupons.edit', $coupon))->assertForbidden();
            $this->post($store, $this->payload())->assertForbidden();
            $this->patch(route('admin.coupons.update', $coupon), $this->payload())->assertForbidden();
        }
        $this->assertDatabaseCount('coupons', 1);
    }

    public function test_coupon_start_may_be_in_the_past_but_expiry_cannot(): void
    {
        $this->actingAs(User::factory()->create(['role' => UserRole::Admin]));
        $this->travelTo(CarbonImmutable::parse('2026-11-22 01:00:00', Coupon::LOCAL_TIMEZONE));

        $this->postJson(route('admin.coupons.store'), $this->payload([
            'starts_at' => '2026-11-21T00:59',
            'ends_at' => '2026-11-22T02:30',
        ]))->assertRedirect();
        $coupon = Coupon::query()->sole();

        $this->travelTo(CarbonImmutable::parse('2026-11-22 02:00:00', Coupon::LOCAL_TIMEZONE));
        $this->patch(route('admin.coupons.update', $coupon), $this->payload([
            'is_active' => '0',
        ]))->assertRedirectToRoute('admin.coupons.index');

        $this->travelTo(CarbonImmutable::parse('2026-11-22 03:00:00', Coupon::LOCAL_TIMEZONE));
        $this->patchJson(route('admin.coupons.update', $coupon), $this->payload([
            'starts_at' => '2026-11-22T02:00',
            'ends_at' => '2026-11-22T02:30',
        ]))->assertUnprocessable()->assertJsonValidationErrors('ends_at');
    }

    public function test_discount_respects_the_time_window_threshold_limits_rounding_and_cap(): void
    {
        $this->actingAs(User::factory()->create(['role' => UserRole::Admin]));
        $this->post(route('admin.coupons.store'), $this->payload())->assertRedirect();
        $coupon = Coupon::query()->sole();
        $start = $coupon->starts_at;
        $end = $coupon->ends_at;

        $this->assertNull($coupon->discountFor(50001, 19, 1, $start->subMicrosecond()));
        $this->assertSame(5000, $coupon->discountFor(50001, 19, 1, $start));
        $this->assertNull($coupon->discountFor(50000, 19, 1, $start));
        $this->assertNull($coupon->discountFor(50001, 20, 1, $start));
        $this->assertNull($coupon->discountFor(50001, 19, 2, $start));
        $this->assertSame(8000, $coupon->discountFor(90000, 19, 1, $end->subMicrosecond()));
        $this->assertNull($coupon->discountFor(50001, 19, 1, $end));
        $this->travelTo($end);
        $this->get(route('admin.coupons.index'))->assertSee('Đã hết hạn')->assertDontSee('Đang hiệu lực');

        $coupon->fill(['discount_value' => 100, 'max_discount_vnd' => null, 'total_limit' => null, 'per_user_limit' => null]);
        $this->assertSame(PHP_INT_MAX, $coupon->discountFor(PHP_INT_MAX, 1000, 1000, $start));
        $coupon->fill(['discount_type' => CouponDiscountType::Fixed, 'discount_value' => 80000]);
        $this->assertSame(50001, $coupon->discountFor(50001, 0, 0, $start));
        $coupon->is_active = false;
        $this->assertNull($coupon->discountFor(50001, 0, 0, $start));
    }

    private function payload(array $overrides = []): array
    {
        return [
            'code' => ' ca-phe_10 ',
            'discount_type' => 'percentage',
            'discount_value' => '10',
            'min_subtotal_vnd' => '50001',
            'max_discount_vnd' => '8000',
            'starts_at' => '2026-11-22T01:30',
            'ends_at' => '2026-11-22T02:30',
            'total_limit' => '20',
            'per_user_limit' => '2',
            'is_active' => '1',
            ...$overrides,
        ];
    }
}
