<?php

namespace Tests\Integration\MySql;

use App\Enums\UserRole;
use App\Models\Category;
use App\Models\Coupon;
use App\Models\User;
use App\Services\ShoppingCart;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CheckoutTest extends MySqlTestCase
{
    public function test_checkout_locks_the_coupon_before_reading_redemptions_and_rejects_the_second_order(): void
    {
        DB::setDefaultConnection($this->primary->getName());
        $this->travelTo(CarbonImmutable::parse('2026-11-23 10:00', 'Asia/Ho_Chi_Minh')->utc());
        config(['checkout.bank_transfer' => [
            'bank_name' => 'Ngân hàng thử', 'account_number' => '00123456', 'account_holder' => 'Quán thử',
        ]]);
        $this->primary->beginTransaction();

        try {
            $category = Category::create(['name' => 'Cà phê thử', 'slug' => Str::uuid()->toString()]);
            $product = $category->products()->create(['name' => 'Món thử', 'slug' => Str::uuid()->toString()]);
            $size = $product->sizes()->create(['code' => 'm', 'label' => 'Vừa', 'price_vnd' => 40000]);
            $coupon = Coupon::create([
                'code' => strtoupper(Str::random(16)), 'discount_type' => 'fixed', 'discount_value' => 10000,
                'starts_at' => now()->subHour(), 'ends_at' => now()->addHour(), 'total_limit' => 1,
                'per_user_limit' => 1,
            ]);

            $first = User::factory()->create();
            $second = User::factory()->create();
            foreach ([$first, $second] as $customer) {
                $customer->forceFill(['linked_bank' => [
                    'bank_name' => 'Ngân hàng thử', 'account_number' => '0123456789', 'account_holder' => 'KHACH THU',
                ]])->save();
                app(ShoppingCart::class)->add($customer, $product->id, ['size_id' => $size->id], 1);
            }

            $firstSubmission = $this->submission($first, $coupon);
            $secondSubmission = $this->submission($second, $coupon);
            $this->primary->enableQueryLog();
            $this->actingAs($first)->post(route('checkout.store'), $firstSubmission)->assertStatus(303);
            $queries = array_map(
                static fn (array $query): string => strtolower($query['query']),
                $this->primary->getQueryLog(),
            );
            $this->primary->disableQueryLog();
            $couponLock = array_key_first(array_filter($queries, static fn (string $sql): bool => str_contains($sql, 'from `coupons`') && str_contains($sql, 'for update')));
            $usageRead = array_key_first(array_filter($queries, static fn (string $sql): bool => str_contains($sql, 'count(*)') && str_contains($sql, 'from `orders`')));
            $this->assertNotNull($couponLock);
            $this->assertNotNull($usageRead);
            $this->assertLessThan($usageRead, $couponLock);

            $this->actingAs($second)->postJson(route('checkout.store'), $secondSubmission)
                ->assertUnprocessable()->assertJsonValidationErrors('coupon_code');
            $order = $first->orders()->sole();
            $this->assertSame(30000, $order->total_vnd);
            $this->assertSame('bank_transfer', $order->payment()->sole()->method->value);
            $this->assertSame('10:15', $order->scheduledPickup()->sole()->scheduled_at->setTimezone('Asia/Ho_Chi_Minh')->format('H:i'));
            $this->assertSame(0, $first->cart()->sole()->items()->count());
            $this->assertSame(1, $second->cart()->sole()->items()->count());
            $this->assertFalse($second->orders()->exists());
            $link = route('orders.show', $order->order_number);
            $this->get($link)->assertNotFound();
            $this->actingAs($first)->get(route('orders.index', ['status' => 'processing']))
                ->assertOk()->assertSee($order->order_number);
            $this->get($link)->assertOk()->assertSee('Món thử')->assertSee('30.000 ₫')->assertSee('Chờ thanh toán');

            $this->primary->flushQueryLog();
            $this->primary->enableQueryLog();
            $this->post(route('orders.cancel', $order->order_number), ['expected_status' => 'pending', 'confirm' => '1'])
                ->assertStatus(303);
            $queries = array_map('strtolower', array_column($this->primary->getQueryLog(), 'query'));
            $this->primary->disableQueryLog();
            $locks = array_values(array_filter($queries, static fn (string $sql): bool => str_contains($sql, 'for update')));
            $this->assertCount(2, $locks);
            $this->assertStringContainsString('from `orders`', $locks[0]);
            $this->assertStringContainsString('from `payments`', $locks[1]);
            $this->assertSame('cancelled', $order->refresh()->status->value);
            $this->assertSame('voided', $order->payment()->sole()->status->value);
            $this->assertSame($coupon->id, $order->coupon_id);

            app(ShoppingCart::class)->add($first, $product->id, ['size_id' => $size->id], 1);
            $afterCancellation = $this->submission($first, $coupon);
            $this->actingAs($second)->post(route('checkout.store'), $secondSubmission)->assertStatus(303);
            $secondOrder = $second->orders()->sole();
            $this->assertSame(30000, $secondOrder->total_vnd);
            $this->actingAs($first)->postJson(route('checkout.store'), $afterCancellation)
                ->assertUnprocessable()->assertJsonValidationErrors('coupon_code');

            $staff = User::factory()->create(['role' => UserRole::Staff]);
            $this->actingAs($staff)->post(route('operations.orders.decide', $secondOrder->order_number), [
                'expected_status' => 'pending', 'decision' => 'rejected', 'reason' => 'Hết nguyên liệu',
            ])->assertStatus(303);
            $this->assertSame('voided', $secondOrder->payment()->sole()->status->value);
            $this->actingAs($first)->post(route('checkout.store'), $afterCancellation)->assertStatus(303);
            $thirdOrder = $first->orders()->where('status', 'pending')->sole();
            $this->assertSame(30000, $thirdOrder->total_vnd);

            app(ShoppingCart::class)->add($second, $product->id, ['size_id' => $size->id], 1);
            $review = [
                'cart_version' => $second->cart()->sole()->version, 'fulfillment_type' => 'pickup', 'method' => 'bank_transfer',
                'pickup_time' => '2026-11-23 10:15',
                'recipient_name' => 'Khách thử', 'phone' => '0901234567', 'coupon_code' => $coupon->code,
            ];
            $this->actingAs($second)->postJson(route('checkout.review'), $review)
                ->assertUnprocessable()->assertJsonValidationErrors('coupon_code');
            $admin = User::factory()->create(['role' => UserRole::Admin]);
            $this->actingAs($admin)->post(route('admin.orders.cancel', $thirdOrder->order_number), [
                'expected_status' => 'pending', 'reason' => 'Không thể phục vụ món',
            ])->assertStatus(303);
            $this->assertSame('cancelled', $thirdOrder->refresh()->status->value);
            $this->assertSame('voided', $thirdOrder->payment()->sole()->status->value);
            $this->assertSame($admin->id, $thirdOrder->statusHistories()->where('to_status', 'cancelled')->sole()->actor_id);
            $afterAdminCancellation = $this->submission($second, $coupon);
            $this->post(route('checkout.store'), $afterAdminCancellation)->assertStatus(303);
            $this->assertSame(30000, $second->orders()->where('status', 'pending')->sole()->total_vnd);
        } finally {
            $this->primary->disableQueryLog();
            $this->primary->rollBack();
        }
    }

    private function submission(User $user, Coupon $coupon): array
    {
        $details = [
            'cart_version' => $user->cart()->sole()->version, 'fulfillment_type' => 'pickup', 'method' => 'bank_transfer',
            'pickup_time' => '2026-11-23 10:15',
            'recipient_name' => 'Khách thử', 'phone' => '0901234567', 'coupon_code' => $coupon->code,
        ];
        $response = $this->actingAs($user)->post(route('checkout.review'), $details)->assertOk();

        return [
            ...$details,
            'checkout_key' => $response->viewData('key'),
            'accepted_total_vnd' => $response->viewData('quote')['order']['total_vnd'],
            'quote_hash' => $response->viewData('quote')['hash'],
        ];
    }
}
