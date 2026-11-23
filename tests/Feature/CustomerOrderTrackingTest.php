<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\UserRole;
use App\Models\Category;
use App\Models\DeliverySetting;
use App\Models\Order;
use App\Models\Topping;
use App\Models\User;
use App\Services\ShoppingCart;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class CustomerOrderTrackingTest extends TestCase
{
    use RefreshDatabase;

    public function test_customers_can_only_list_and_open_orders_they_own(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();
        $aliceOrder = $this->order($alice, 'CF-ALICE', OrderStatus::Pending);
        $bobOrder = $this->order($bob, 'CF-BOB', OrderStatus::Confirmed);
        $aliceUrl = route('orders.show', $aliceOrder->order_number);
        $bobUrl = route('orders.show', $bobOrder->order_number);

        $this->get(route('orders.index'))->assertRedirectToRoute('login');
        $this->get($aliceUrl)->assertRedirectToRoute('login');
        foreach ([
            ['role' => UserRole::Staff],
            ['role' => UserRole::Admin],
            ['role' => UserRole::Customer, 'is_active' => false],
        ] as $attributes) {
            $this->actingAs(User::factory()->create($attributes))->get(route('orders.index'))->assertForbidden();
            $this->get($aliceUrl)->assertForbidden();
        }

        $this->actingAs($alice)->get(route('dashboard'))->assertSee('Đơn của tôi');
        $this->get(route('orders.index'))->assertOk()->assertSee($aliceOrder->order_number)->assertDontSee($bobOrder->order_number);
        $this->get($aliceUrl)->assertOk()->assertSee($aliceOrder->order_number);
        $foreign = $this->getJson($bobUrl)->assertNotFound();
        $missing = $this->getJson(route('orders.show', 'CF-MISSING'))->assertNotFound();
        $this->assertSame($missing->json('message'), $foreign->json('message'));

        $this->actingAs($bob)->get(route('orders.index'))->assertOk()->assertSee($bobOrder->order_number)->assertDontSee($aliceOrder->order_number);
        $this->get($aliceUrl)->assertNotFound();
    }

    public function test_status_filters_keep_their_selection_across_pages(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->get(route('orders.index'))->assertOk()->assertSee('Bạn chưa có đơn hàng.');
        $this->get(route('orders.index', ['status' => 'finished']))->assertOk()->assertSee('Bạn không có đơn đã kết thúc.');
        $this->get(route('orders.index', ['status' => ['finished']]))->assertUnprocessable();
        $this->get(route('orders.index', ['status' => 'unknown']))->assertUnprocessable();

        $this->freezeTime();
        foreach (range(1, 11) as $number) {
            $this->order($user, 'CF-FINISHED-'.$number, match ($number) {
                1 => OrderStatus::Cancelled,
                2 => OrderStatus::Rejected,
                default => OrderStatus::Completed,
            });
        }
        $this->order($user, 'CF-PROCESSING', OrderStatus::OutForDelivery);
        $this->order(User::factory()->create(), 'CF-FOREIGN', OrderStatus::Cancelled);

        $finished = $this->get(route('orders.index', ['status' => 'finished']))->assertOk()
            ->assertSee('status=finished&amp;page=2', escape: false)
            ->assertDontSee('CF-PROCESSING')->assertDontSee('CF-FOREIGN');
        $orders = $finished->viewData('orders');
        $this->assertSame(11, $orders->total());
        $this->assertSame(10, $orders->count());
        $this->assertSame('CF-FINISHED-11', $orders->first()->order_number);

        $this->get(route('orders.index', ['status' => 'finished', 'page' => 2]))->assertOk()
            ->assertSee('CF-FINISHED-1')->assertSee('Đã hủy')->assertDontSee('CF-FINISHED-11');
        $this->get(route('orders.index', ['status' => 'processing']))->assertOk()
            ->assertSee('CF-PROCESSING')->assertSee('Đang giao')->assertDontSee('CF-FINISHED');
    }

    public function test_detail_reads_public_history_and_keeps_checkout_snapshots_after_edits(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-21 09:00:00 UTC'));
        $customer = User::factory()->create();
        $operator = User::factory()->create(['name' => 'Tên nội bộ nhân viên', 'role' => UserRole::Admin]);
        $category = Category::create(['name' => 'Cà phê', 'slug' => 'coffee']);
        $product = $category->products()->create(['name' => 'Cà phê sữa', 'slug' => 'ca-phe-sua', 'allows_ice' => true]);
        $size = $product->sizes()->create(['code' => 'm', 'label' => 'Cỡ vừa', 'price_vnd' => 38000]);
        $topping = Topping::create(['name' => 'Thạch cà phê', 'code' => 'thach', 'price_vnd' => 7000]);
        $product->toppings()->attach($topping);
        app(ShoppingCart::class)->add($customer, $product->id, [
            'size_id' => $size->id, 'ice_level' => 'less', 'topping_ids' => [$topping->id],
        ], 2);
        $address = $customer->addresses()->create([
            'recipient_name' => 'Khách nhận', 'phone' => '0901234567', 'province_code' => '79',
            'district_code' => '760', 'ward_code' => '26734', 'address_line' => '10 Nguyễn Huệ',
        ]);
        $delivery = DeliverySetting::query()->sole();
        $delivery->update(['delivery_enabled' => true, 'flat_fee_vnd' => 23000, 'eligible_districts' => ['760']]);

        $details = [
            'cart_version' => 1, 'fulfillment_type' => 'delivery', 'method' => 'cod', 'address_id' => $address->id,
            'recipient_name' => 'Khách nhận', 'phone' => '0901234567',
        ];
        $review = $this->actingAs($customer)->post(route('checkout.review'), $details)->assertOk();
        $this->post(route('checkout.store'), [
            ...$details, 'checkout_key' => $review->viewData('key'),
            'accepted_total_vnd' => 113000, 'quote_hash' => $review->viewData('quote')['hash'],
        ])->assertStatus(303);
        $order = $customer->orders()->sole();
        $url = route('orders.show', $order->order_number);
        $this->get(route('checkout.confirmation', $order->order_number))->assertOk()->assertSee($url);

        $product->update(['name' => 'Tên món đã đổi']);
        $size->update(['label' => 'Size đã đổi', 'price_vnd' => 55000]);
        $topping->update(['name' => 'Topping đã đổi', 'price_vnd' => 12000]);
        $address->update(['address_line' => 'Địa chỉ đã đổi']);
        $delivery->update(['flat_fee_vnd' => 31000]);

        $this->travel(5)->minutes();
        $order->statusHistories()->create([
            'actor_id' => $operator->id, 'from_status' => 'pending', 'to_status' => 'confirmed', 'reason' => 'Ghi chú nội bộ quầy',
        ]);
        $this->travel(5)->minutes();
        $order->statusHistories()->create([
            'actor_id' => $operator->id, 'from_status' => 'confirmed', 'to_status' => 'cancelled', 'reason' => 'Lý do nội bộ hủy',
        ]);
        $order->update(['status' => OrderStatus::Cancelled]);
        $payment = $order->payment()->sole();
        $payment->forceFill([
            'status' => PaymentStatus::Refunded, 'collection_reference' => 'THU-RIENG-435',
            'refund_reference' => 'HOAN-RIENG-678',
        ])->save();
        $payment->statusHistories()->create([
            'actor_id' => $operator->id, 'from_status' => 'refund_pending', 'to_status' => 'refunded', 'reason' => 'Ghi chú hoàn tiền riêng',
        ]);

        DB::enableQueryLog();
        try {
            $response = $this->get($url)->assertOk()->assertSee('Đã hoàn tiền')
                ->assertSeeInOrder(['Chờ xác nhận', '16:00 21/11/2026', 'Đã xác nhận', '16:05 21/11/2026', 'Đã hủy', '16:10 21/11/2026']);
            foreach (['Cà phê sữa', 'Cỡ vừa', 'Thạch cà phê', '38.000 ₫', '7.000 ₫', '90.000 ₫', '23.000 ₫', '113.000 ₫', '10 Nguyễn Huệ', 'Phường Tân Định'] as $public) {
                $response->assertSee($public);
            }
            foreach ([
                'Tên món đã đổi', 'Size đã đổi', 'Topping đã đổi', 'Địa chỉ đã đổi', '31.000 ₫',
                'Tên nội bộ nhân viên', 'Ghi chú nội bộ quầy', 'Lý do nội bộ hủy', 'Ghi chú hoàn tiền riêng', 'THU-RIENG-435', 'HOAN-RIENG-678',
            ] as $private) {
                $response->assertDontSee($private);
            }
            $this->assertNotEmpty(DB::getQueryLog());
            foreach (DB::getQueryLog() as $query) {
                $this->assertStringStartsWith('select', strtolower(ltrim($query['query'])));
            }
        } finally {
            DB::disableQueryLog();
            DB::flushQueryLog();
        }
    }

    private function order(User $owner, string $number, OrderStatus $status): Order
    {
        return $owner->orders()->create([
            'order_number' => $number, 'checkout_key' => Str::uuid()->toString(),
            'request_hash' => hash('sha256', $number), 'recipient_name' => 'Khách nhận', 'phone' => '0901234567',
            'fulfillment_type' => 'pickup', 'status' => $status, 'subtotal_vnd' => 40000,
            'discount_vnd' => 0, 'delivery_fee_vnd' => 0, 'total_vnd' => 40000,
        ]);
    }
}
