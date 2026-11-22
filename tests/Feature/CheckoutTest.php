<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\UserRole;
use App\Models\Cart;
use App\Models\Category;
use App\Models\Coupon;
use App\Models\DeliverySetting;
use App\Models\Order;
use App\Models\OrderNumberSequence;
use App\Models\Topping;
use App\Models\User;
use App\Models\UserAddress;
use App\Services\OrderNumbers;
use App\Services\ShoppingCart;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class CheckoutTest extends TestCase
{
    use RefreshDatabase;

    public static function combinations(): array
    {
        return [
            'pickup COD' => ['pickup', 'cod', 0],
            'pickup bank' => ['pickup', 'bank_transfer', 0],
            'delivery COD' => ['delivery', 'cod', 23000],
            'delivery bank' => ['delivery', 'bank_transfer', 23000],
        ];
    }

    #[DataProvider('combinations')]
    public function test_checkout_saves_snapshots_pending_records_and_totals(string $fulfillment, string $method, int $fee): void
    {
        [$user, $product, $topping, $address] = $this->basket();
        $coupon = $this->coupon();
        $this->delivery();
        $this->bank();
        $cart = $user->cart()->sole();
        $this->actingAs($user)->get(route('cart.index'))->assertSee('Tiến hành đặt hàng');
        $this->get(route('checkout.create'))->assertOk()->assertSee('Xem tổng tiền')
            ->assertSee('Chuyển khoản ngân hàng')->assertSee('10 Nguyễn Huệ');

        $submission = $this->review($user, [
            'fulfillment_type' => $fulfillment, 'method' => $method, 'address_id' => $address->id, 'coupon_code' => ' giam10 ',
        ]);
        $this->assertDatabaseCount('orders', 0);
        $this->assertSame(81000 + $fee, $submission['accepted_total_vnd']);
        if ($fulfillment === 'pickup') {
            $this->assertArrayNotHasKey('address_id', $submission);
        }
        $response = $this->post(route('checkout.store'), $submission);
        $order = $user->orders()->sole();
        $this->assertSame('HD0000001', $order->order_number);
        $response->assertStatus(303)->assertRedirectToRoute('checkout.confirmation', $order->order_number);
        $this->assertSame(OrderStatus::Pending, $order->status);
        $this->assertSame($fulfillment, $order->fulfillment_type->value);
        $this->assertSame(90000, $order->subtotal_vnd);
        $this->assertSame(9000, $order->discount_vnd);
        $this->assertSame($fee, $order->delivery_fee_vnd);
        $this->assertSame(81000 + $fee, $order->total_vnd);
        $this->assertSame('GIAM10', $order->coupon_code);
        $this->assertSame('Khách nhận', $order->recipient_name);
        $this->assertSame('0901234567', $order->phone);

        $item = $order->items()->sole();
        $this->assertSame('Cà phê sữa', $item->product_name);
        $this->assertSame('Cỡ vừa', $item->size_label);
        $this->assertSame('less', $item->ice_level);
        $this->assertSame(2, $item->quantity);
        $this->assertSame(38000, $item->base_unit_price_vnd);
        $this->assertSame(90000, $item->line_total_vnd);
        $this->assertSame('Thạch cà phê', $item->toppings()->sole()->topping_name);
        $this->assertSame(7000, $item->toppings()->sole()->unit_price_vnd);
        $payment = $order->payment()->sole();
        $this->assertSame($method, $payment->method->value);
        $this->assertSame(PaymentStatus::Pending, $payment->status);
        $this->assertSame($order->total_vnd, $payment->amount_vnd);
        $this->assertDatabaseHas('order_status_histories', ['order_id' => $order->id, 'actor_id' => $user->id, 'from_status' => null, 'to_status' => 'pending']);
        $this->assertDatabaseHas('payment_status_histories', ['payment_id' => $payment->id, 'actor_id' => $user->id, 'from_status' => null, 'to_status' => 'pending']);
        $this->assertSame(0, $cart->items()->count());
        $this->assertSame(2, $cart->refresh()->version);
        $this->assertDatabaseCount('cart_item_toppings', 0);

        $address->update(['address_line' => 'Địa chỉ mới']);
        $this->delivery(30000);
        $product->update(['name' => 'Tên mới']);
        $product->sizes()->sole()->update(['label' => 'Size mới', 'price_vnd' => 78000]);
        $topping->update(['name' => 'Topping mới', 'price_vnd' => 9000]);
        $coupon->update(['code' => 'MAMOI']);
        config(['checkout.bank_transfer' => []]);

        $order->refresh();
        if ($fulfillment === 'delivery') {
            $this->assertSame('10 Nguyễn Huệ', $order->address_snapshot['address_line']);
            $this->assertSame('Quận 1', $order->address_snapshot['district_name']);
        } else {
            $this->assertNull($order->address_snapshot);
        }
        $this->assertSame($fee, $order->delivery_fee_vnd);
        $this->assertSame('GIAM10', $order->coupon_code);
        $this->assertSame('Cà phê sữa', $item->refresh()->product_name);
        $this->assertSame('Cỡ vừa', $item->size_label);
        $this->assertSame(38000, $item->base_unit_price_vnd);
        $this->assertSame('Thạch cà phê', $item->toppings()->sole()->topping_name);
        $this->assertSame(7000, $item->toppings()->sole()->unit_price_vnd);

        $confirmation = $this->get(route('checkout.confirmation', $order->order_number))
            ->assertOk()->assertSee($order->order_number)->assertSee(number_format($order->total_vnd, 0, ',', '.').' ₫');
        if ($method === 'bank_transfer') {
            $confirmation->assertSee('Thông tin chuyển khoản')->assertSee('Ngân hàng thử')->assertSee('00123456');
        } else {
            $confirmation->assertDontSee('Thông tin chuyển khoản');
            $this->assertNull($payment->transfer_details);
        }

        $this->post(route('checkout.store'), $submission)->assertRedirectToRoute('checkout.confirmation', $order->order_number);
        $this->postJson(route('checkout.store'), [...$submission, 'recipient_name' => 'Nội dung khác'])
            ->assertConflict()->assertJsonValidationErrors('checkout_key');
        $this->assertDatabaseCount('orders', 1);
        $this->assertSame(1, OrderNumberSequence::query()->findOrFail(1)->last_number);
        $this->assertSame(2, $cart->refresh()->version);
    }

    public function test_stale_cart_changed_total_and_equal_total_with_changed_prices_are_rejected(): void
    {
        [$user, $product, $topping] = $this->basket();
        $submission = $this->review($user);
        $this->postJson(route('checkout.store'), [...$submission, 'accepted_total_vnd' => 1])
            ->assertConflict()->assertJsonValidationErrors('accepted_total_vnd');

        $size = $product->sizes()->sole();
        $size->update(['price_vnd' => 39000]);
        $topping->update(['price_vnd' => 6000]);
        $this->postJson(route('checkout.store'), $submission)->assertConflict()->assertJsonValidationErrors('accepted_total_vnd');

        $topping->update(['is_active' => false]);
        $this->postJson(route('checkout.store'), $submission)->assertUnprocessable()->assertJsonValidationErrors('cart_version');
        $topping->update(['is_active' => true]);
        $cart = $user->cart()->sole();
        $item = $cart->items()->sole();
        $this->patch(route('cart.update', $item), ['version' => 1, 'quantity' => 3])->assertRedirect();
        $this->postJson(route('checkout.store'), $submission)->assertConflict()->assertJsonValidationErrors('cart_version');
        $this->assertDatabaseCount('orders', 0);
        $this->assertSame(3, $item->refresh()->quantity);
    }

    public function test_only_the_customer_can_use_their_address_and_view_their_confirmation(): void
    {
        [$user, , , $address] = $this->basket();
        $outsider = User::factory()->create();
        $otherAddress = $this->address($outsider);
        $this->get(route('checkout.create'))->assertRedirectToRoute('login');
        $this->actingAs(User::factory()->create(['role' => UserRole::Staff]))->get(route('checkout.create'))->assertForbidden();
        $this->actingAs($user);
        $this->delivery();

        foreach ([$otherAddress->id, $this->address($user, '761', '26764')->id] as $invalidAddress) {
            $this->postJson(route('checkout.review'), $this->details([
                'fulfillment_type' => 'delivery', 'address_id' => $invalidAddress,
            ]))->assertUnprocessable()->assertJsonValidationErrors('address_id');
        }

        $this->delivery(0, false);
        $this->postJson(route('checkout.review'), $this->details([
            'fulfillment_type' => 'delivery', 'address_id' => $address->id,
        ]))->assertUnprocessable()->assertJsonValidationErrors('address_id');
        config(['checkout.bank_transfer' => []]);
        $this->get(route('checkout.create'))->assertDontSee('Chuyển khoản ngân hàng');
        $this->postJson(route('checkout.review'), $this->details(['method' => 'bank_transfer']))
            ->assertUnprocessable()->assertJsonValidationErrors('method');

        $this->post(route('checkout.store'), $this->review($user))->assertRedirect();
        $order = $user->orders()->sole();
        $this->actingAs($outsider)->get(route('checkout.confirmation', $order->order_number))->assertNotFound();
        $this->get(route('checkout.create'))->assertRedirectToRoute('cart.index');
    }

    public function test_outside_area_error_appears_once_below_saved_address_and_keeps_the_cart(): void
    {
        [$user] = $this->basket();
        $address = $this->address($user, '761', '26764');
        $this->delivery();
        $cart = $user->cart()->sole();
        $message = 'Địa chỉ chưa được hỗ trợ giao hàng. Vui lòng chọn địa chỉ khác hoặc nhận tại quán.';

        $response = $this->actingAs($user)->followingRedirects()->post(route('checkout.review'), $this->details([
            'fulfillment_type' => 'delivery', 'address_id' => $address->id,
        ]))->assertOk()->assertSee('Địa chỉ đã lưu')->assertSee($message);

        $this->assertSame(1, substr_count($response->getContent(), $message), 'The address error should be rendered once.');
        $response->assertDontSeeHtml('<li>'.$message.'</li>');
        $this->assertDatabaseCount('orders', 0);
        $this->assertSame(1, $cart->refresh()->version);
        $this->assertSame(2, $cart->items()->sole()->quantity);
    }

    public function test_missing_address_keeps_its_error_in_the_summary_when_there_is_no_select(): void
    {
        [$user] = $this->basket(withAddress: false);
        $this->actingAs($user)->followingRedirects()->post(route('checkout.review'), $this->details([
            'fulfillment_type' => 'delivery',
        ]))->assertOk()
            ->assertSee('thêm địa chỉ giao hàng')
            ->assertSeeHtml('<li>'.__('validation.required', ['attribute' => 'địa chỉ giao hàng']).'</li>');
    }

    public function test_failed_write_rolls_back_order_payment_histories_and_cart_deletion(): void
    {
        [$user] = $this->basket();
        $submission = $this->review($user);
        Cart::updated(static function (): never {
            throw new RuntimeException('Simulated failure after the cart was emptied');
        });

        $this->postJson(route('checkout.store'), $submission)->assertServerError();
        foreach (['orders', 'order_items', 'order_item_toppings', 'payments', 'order_status_histories', 'payment_status_histories'] as $table) {
            $this->assertDatabaseCount($table, 0);
        }
        $cart = $user->cart()->sole();
        $this->assertSame(1, $cart->version);
        $this->assertSame(2, $cart->items()->sole()->quantity);
        $this->assertDatabaseCount('cart_item_toppings', 1);
        $this->assertSame(0, OrderNumberSequence::query()->findOrFail(1)->last_number);
    }

    public function test_coupon_usage_counts_only_orders_that_still_consume_a_redemption(): void
    {
        [$first] = $this->basket();
        [$second] = $this->basket();
        $this->coupon(['total_limit' => 1, 'per_user_limit' => 1]);
        $firstSubmission = $this->review($first, ['coupon_code' => 'GIAM10']);
        $secondSubmission = $this->review($second, ['coupon_code' => 'GIAM10']);

        $this->actingAs($first)->post(route('checkout.store'), $firstSubmission)->assertRedirect();
        $this->actingAs($second)->postJson(route('checkout.store'), $secondSubmission)
            ->assertUnprocessable()->assertJsonValidationErrors('coupon_code');
        $this->assertSame(1, $second->cart()->sole()->items()->count());

        $firstOrder = $first->orders()->sole();
        $firstOrder->status = OrderStatus::Cancelled;
        $firstOrder->save();
        $this->post(route('checkout.store'), $secondSubmission)->assertRedirect();
        $this->assertDatabaseCount('orders', 2);
    }

    public function test_zero_total_confirmation_skips_transfer_instructions(): void
    {
        [$user] = $this->basket();
        $this->coupon(['discount_value' => 100]);
        $this->bank();
        $submission = $this->review($user, ['method' => 'bank_transfer', 'coupon_code' => 'GIAM10']);
        $this->get(route('checkout.create'))->assertOk()->assertSeeHtml('value="GIAM10"');
        $this->assertSame(0, $submission['accepted_total_vnd']);
        $this->post(route('checkout.store'), $submission)->assertStatus(303);
        $order = $user->orders()->sole();
        $this->assertSame(0, $order->payment()->sole()->amount_vnd);
        $this->get(route('checkout.confirmation', $order->order_number))
            ->assertOk()->assertSee('Tổng tiền bằng 0 ₫. Bạn không cần chuyển khoản.')->assertDontSee('00123456');
    }

    public function test_checkout_exposes_the_demo_gateway_only_in_local_and_testing(): void
    {
        [$user] = $this->basket();
        $this->actingAs($user)->get(route('checkout.create'))->assertOk()->assertSee('Cổng thanh toán mô phỏng');
        $details = ['method' => 'demo_gateway'];
        $this->app->instance('env', 'production');
        $this->get(route('checkout.create'))->assertOk()->assertDontSee('Cổng thanh toán mô phỏng');
        $this->postJson(route('checkout.review'), $this->details($details), ['Sec-Fetch-Site' => 'same-origin'])
            ->assertUnprocessable()->assertJsonValidationErrors('method');

        $this->app->instance('env', 'testing');
        $this->post(route('checkout.store'), $this->review($user, $details))->assertStatus(303);
        $order = $user->orders()->sole();
        $this->assertDatabaseHas('payments', ['order_id' => $order->id, 'method' => 'demo_gateway', 'status' => 'pending']);
        $this->get(route('checkout.confirmation', $order->order_number))->assertOk()->assertSee('Mở cổng thanh toán mô phỏng');
    }

    private function review(User $user, array $overrides = []): array
    {
        $review = $this->actingAs($user)->post(route('checkout.review'), $this->details($overrides))
            ->assertOk()->assertSee('Xác nhận đặt hàng');

        return array_filter([
            ...$review->viewData('details')->fields(),
            'checkout_key' => $review->viewData('key'),
            'accepted_total_vnd' => $review->viewData('quote')['order']['total_vnd'],
            'quote_hash' => $review->viewData('quote')['hash'],
        ], static fn ($value) => $value !== null);
    }

    public function test_order_numbers_increase_across_customers_and_are_not_reused_after_cancellation(): void
    {
        [$first] = $this->basket();
        [$second] = $this->basket();
        $submission = $this->review($first);
        $this->post(route('checkout.store'), $submission)->assertRedirectToRoute('checkout.confirmation', 'HD0000001');
        $this->post(route('checkout.store'), $submission)->assertRedirectToRoute('checkout.confirmation', 'HD0000001');
        $this->post(route('orders.cancel', 'HD0000001'), ['expected_status' => 'pending', 'confirm' => '1'])->assertStatus(303);
        $next = $this->review($second);
        $this->post(route('checkout.store'), $next)->assertRedirectToRoute('checkout.confirmation', 'HD0000002');
        $this->assertSame(['HD0000001', 'HD0000002'], Order::query()->orderBy('id')->pluck('order_number')->all());
        $this->get(route('orders.show', 'HD0000002'))->assertOk()->assertSee('HD0000002');
        $this->actingAs(User::factory()->create(['role' => UserRole::Staff]))
            ->get(route('operations.invoices.orders.show', 'HD0000002'))->assertOk()->assertSee('Hóa đơn HD0000002');
    }

    public function test_order_number_rolls_over_decimal_digits_and_stops_at_seven_digits(): void
    {
        $sequence = OrderNumberSequence::query()->findOrFail(1);
        $sequence->last_number = 9;
        $sequence->save();
        $this->assertSame('HD0000010', app(OrderNumbers::class)->next());
        $sequence->last_number = 9999998;
        $sequence->save();
        $this->assertSame('HD9999999', app(OrderNumbers::class)->next());
        $this->expectException(ValidationException::class);
        app(OrderNumbers::class)->next();
    }

    private function details(array $overrides = []): array
    {
        return [
            'cart_version' => '1', 'fulfillment_type' => 'pickup', 'method' => 'cod',
            'recipient_name' => 'Khách nhận', 'phone' => '0901234567',
            ...$overrides,
        ];
    }

    private function basket(bool $withAddress = true): array
    {
        $user = User::factory()->create();
        $category = Category::create(['name' => 'Cà phê', 'slug' => 'ca-phe-'.$user->id]);
        $product = $category->products()->create(['name' => 'Cà phê sữa', 'slug' => 'ca-phe-sua-'.$user->id, 'allows_ice' => true]);
        $size = $product->sizes()->create(['code' => 'm', 'label' => 'Cỡ vừa', 'price_vnd' => 38000]);
        $topping = Topping::create(['code' => 'thach-'.$user->id, 'name' => 'Thạch cà phê', 'price_vnd' => 7000]);
        $product->toppings()->attach($topping);
        app(ShoppingCart::class)->add($user, $product->id, [
            'size_id' => $size->id, 'ice_level' => 'less', 'topping_ids' => [$topping->id],
        ], 2);

        return [$user, $product, $topping, $withAddress ? $this->address($user) : null];
    }

    private function address(User $user, string $district = '760', string $ward = '26734'): UserAddress
    {
        return $user->addresses()->create([
            'recipient_name' => 'Khách nhận', 'phone' => '0901234567', 'province_code' => '79',
            'district_code' => $district, 'ward_code' => $ward, 'address_line' => '10 Nguyễn Huệ',
        ]);
    }

    private function coupon(array $overrides = []): Coupon
    {
        return Coupon::create([
            'code' => 'GIAM10', 'discount_type' => 'percentage', 'discount_value' => 10,
            'starts_at' => now()->subHour(), 'ends_at' => now()->addHour(), ...$overrides,
        ]);
    }

    private function delivery(int $fee = 23000, bool $enabled = true): void
    {
        DeliverySetting::query()->sole()->update([
            'delivery_enabled' => $enabled, 'flat_fee_vnd' => $fee, 'eligible_districts' => ['760'],
        ]);
    }

    private function bank(): void
    {
        config(['checkout.bank_transfer' => [
            'bank_name' => 'Ngân hàng thử', 'account_number' => '00123456', 'account_holder' => 'Cửa hàng thử',
        ]]);
    }
}
