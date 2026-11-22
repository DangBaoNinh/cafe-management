<?php

namespace Tests\Support;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductSize;
use App\Models\Topping;
use App\Models\User;
use Illuminate\Support\Str;

trait BestSellerFixture
{
    private function makeCatalogProduct(Category $category, string $name, bool $active = true): Product
    {
        $product = $category->products()->create(['name' => $name, 'slug' => Str::ulid()->toBase32(), 'is_active' => $active]);
        $product->sizes()->create(['code' => 'm', 'label' => 'Vừa', 'price_vnd' => 35000]);

        return $product;
    }

    /** @param list<array{ProductSize, int}> $lines */
    private function recordSale(User $customer, string $completedAt, array $lines, string $status = 'completed', ?string $payment = 'paid', bool $withToppings = false): void
    {
        $number = 'CF-'.Str::ulid();
        $order = $customer->orders()->make([
            'order_number' => $number, 'checkout_key' => Str::uuid()->toString(), 'request_hash' => hash('sha256', $number),
            'recipient_name' => 'Thông tin nội bộ UC28', 'phone' => '0901234567', 'fulfillment_type' => 'pickup',
            'status' => $status, 'subtotal_vnd' => 35000, 'discount_vnd' => 0, 'delivery_fee_vnd' => 0, 'total_vnd' => 35000,
        ]);
        $order->forceFill(['completed_at' => $completedAt, 'created_at' => '2026-08-01 00:00:00'])->save();
        if ($payment !== null) {
            $order->payment()->create(['method' => 'cod', 'status' => $payment, 'amount_vnd' => 35000]);
        }
        foreach ($lines as [$size, $quantity]) {
            $line = $order->items()->create([
                'product_size_id' => $size->id, 'product_name' => 'Tên món tại thời điểm bán', 'size_label' => $size->label,
                'quantity' => $quantity, 'base_unit_price_vnd' => $size->price_vnd, 'line_total_vnd' => $size->price_vnd * $quantity,
            ]);
            if ($withToppings) {
                foreach (['Thạch', 'Kem'] as $name) {
                    $topping = Topping::create(['code' => Str::random(12), 'name' => $name, 'price_vnd' => 5000]);
                    $line->toppings()->create(['topping_id' => $topping->id, 'topping_name' => $name, 'unit_price_vnd' => 5000]);
                }
            }
        }
    }
}
