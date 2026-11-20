<?php

namespace Tests\Integration\MySql;

use App\Models\Category;
use App\Models\Coupon;
use App\Models\User;
use App\Services\ShoppingCart;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CheckoutTest extends MySqlTestCase
{
    public function test_checkout_locks_the_coupon_before_reading_redemptions_and_rejects_the_second_order(): void
    {
        DB::setDefaultConnection($this->primary->getName());
        $this->primary->beginTransaction();

        try {
            $category = Category::create(['name' => 'Cà phê thử', 'slug' => Str::uuid()->toString()]);
            $product = $category->products()->create(['name' => 'Món thử', 'slug' => Str::uuid()->toString()]);
            $size = $product->sizes()->create(['code' => 'm', 'label' => 'Vừa', 'price_vnd' => 40000]);
            $coupon = Coupon::create([
                'code' => strtoupper(Str::random(16)), 'discount_type' => 'fixed', 'discount_value' => 10000,
                'starts_at' => now()->subHour(), 'ends_at' => now()->addHour(), 'total_limit' => 1,
            ]);

            $first = User::factory()->create();
            $second = User::factory()->create();
            foreach ([$first, $second] as $customer) {
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
            $this->assertSame(0, $first->cart()->sole()->items()->count());
            $this->assertSame(1, $second->cart()->sole()->items()->count());
            $this->assertFalse($second->orders()->exists());
            $link = route('orders.show', $order->order_number);
            $this->get($link)->assertNotFound();
            $this->actingAs($first)->get(route('orders.index', ['status' => 'processing']))
                ->assertOk()->assertSee($order->order_number);
            $this->get($link)->assertOk()->assertSee('Món thử')->assertSee('30.000 ₫')->assertSee('Chờ thanh toán');
        } finally {
            $this->primary->disableQueryLog();
            $this->primary->rollBack();
        }
    }

    private function submission(User $user, Coupon $coupon): array
    {
        $details = [
            'cart_version' => 1, 'fulfillment_type' => 'pickup', 'method' => 'cod',
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
