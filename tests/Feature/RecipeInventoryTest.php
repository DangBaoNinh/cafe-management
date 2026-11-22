<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Models\Category;
use App\Models\Ingredient;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\Product;
use App\Models\ProductSize;
use App\Models\Topping;
use App\Models\User;
use App\Services\ShoppingCart;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\Support\InventoryOrderFixture;
use Tests\TestCase;

class RecipeInventoryTest extends TestCase
{
    use InventoryOrderFixture, RefreshDatabase;

    private const START = ['expected_status' => 'confirmed', 'target_status' => 'preparing'];

    public function test_admin_can_initialize_and_reconcile_stock_with_an_auditable_ledger(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $store = route('admin.inventory.store');
        $index = route('admin.inventory.index');
        $fields = ['name' => 'Sữa tươi', 'unit' => 'ml', 'low_threshold' => '9,75'];
        $this->get($index)->assertRedirectToRoute('login');
        foreach ([User::factory()->create(['role' => UserRole::Staff]), User::factory()->create(), User::factory()->create(['role' => UserRole::Admin, 'is_active' => false])] as $actor) {
            $this->actingAs($actor)->get($index)->assertForbidden();
            $this->postJson($store, $fields)->assertForbidden();
        }
        $this->actingAs($admin)->get($index)->assertOk()->assertSee('Thêm nguyên liệu');
        $this->postJson($store, [...$fields, 'low_threshold' => '0,1234'])->assertUnprocessable()->assertJsonValidationErrors('low_threshold');
        $this->post($store, $fields)->assertStatus(303);
        $this->postJson($store, $fields)->assertUnprocessable()->assertJsonValidationErrors('name');
        $milk = Ingredient::sole();
        $this->get($index)->assertOk()->assertSee('Chưa ghi tồn đầu kỳ');
        $this->patch(route('admin.inventory.update', $milk), [...$fields, 'unit' => 'g'])->assertStatus(303);
        $this->assertSame('ml', $milk->refresh()->unit->value);

        $movement = route('admin.inventory.record', $milk);
        $receipt = $this->movement($milk, 'receipt', '10,125', 'Nhập đầu kỳ');
        $this->post($movement, $receipt)->assertStatus(303);
        $this->post($movement, $receipt)->assertStatus(303);
        $this->postJson($movement, [...$receipt, 'quantity' => '20'])->assertUnprocessable()->assertJsonValidationErrors('quantity');
        $this->postJson($movement, $this->movement($milk, 'count', '7', 'Phiếu cũ'))->assertUnprocessable()->assertJsonValidationErrors('quantity');
        $this->postJson($movement, $this->movement($milk->refresh(), 'adjustment', '-10,126', 'Vượt tồn'))->assertUnprocessable()->assertJsonValidationErrors('quantity');
        $this->postJson($movement, $this->movement($milk, 'adjustment', '1', ''))->assertUnprocessable()->assertJsonValidationErrors('reason');
        $this->postJson($movement, $this->movement($milk, 'consumption', '1', 'Giả lập'))->assertUnprocessable()->assertJsonValidationErrors('kind');
        $this->post($movement, $this->movement($milk, 'count', '9,5', 'Kiểm thực tế'))->assertStatus(303);
        $this->post($movement, $this->movement($milk, 'adjustment', '0,125', 'Trả lại nguyên liệu'))->assertStatus(303);

        $this->assertSame(9625, $milk->refresh()->stock);
        $this->assertSame([10125, -625, 125], $milk->movements()->orderBy('id')->pluck('quantity_change')->all());
        $this->assertSame([$admin->id, $admin->id, $admin->id], $milk->movements()->orderBy('id')->pluck('actor_id')->all());
        $this->get($index)->assertOk()->assertSee('Dưới ngưỡng');
        $this->get(route('admin.inventory.show', $milk))->assertOk()->assertSee('9,625 ml')->assertSee('Trả lại nguyên liệu')->assertSee($admin->name);
    }

    public function test_checkout_saves_size_and_topping_quantities_and_cancellation_preserves_the_ledger(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $staff = User::factory()->create(['role' => UserRole::Staff]);
        $customer = User::factory()->create();
        [$product, $size, $topping] = $this->catalog();
        $milk = $this->ingredient('Sữa', 'ml');
        $coffee = $this->ingredient('Cà phê', 'g');
        $sizeRecipe = route('admin.inventory.recipes.store', ['size', $size]);
        $toppingRecipe = route('admin.inventory.recipes.store', ['topping', $topping]);
        $this->actingAs($staff)->postJson($sizeRecipe, ['ingredient_id' => $milk->id, 'quantity' => '10'])->assertForbidden();
        $this->actingAs($admin)->get(route('admin.products.options', $product))->assertOk()->assertSee($sizeRecipe);
        $this->get(route('admin.toppings.index'))->assertOk()->assertSee($toppingRecipe);
        $this->postJson($sizeRecipe, ['ingredient_id' => $milk->id, 'quantity' => '0'])->assertUnprocessable()->assertJsonValidationErrors('quantity');
        $this->post($sizeRecipe, ['ingredient_id' => $milk->id, 'quantity' => '10,125'])->assertStatus(303);
        $this->post($sizeRecipe, ['ingredient_id' => $coffee->id, 'quantity' => '2,050'])->assertStatus(303);
        $this->post($toppingRecipe, ['ingredient_id' => $milk->id, 'quantity' => '0,375'])->assertStatus(303);
        $this->get($toppingRecipe)->assertOk()->assertSee('0,375 ml');
        $this->initialize($milk, '50');
        $this->initialize($coffee, '10');
        $this->post(route('admin.inventory.enable'))->assertStatus(303);

        $order = $this->checkout($customer, $product, $size, $topping, 3);
        $cancelled = $this->checkout($customer, $product, $size, $topping, 1);
        $this->assertSame(['quantities' => [$milk->id => 31500, $coffee->id => 6150], 'missing' => []], $order->inventory_snapshot);
        $this->actingAs($admin)->post($sizeRecipe, ['ingredient_id' => $milk->id, 'quantity' => '100'])->assertStatus(303);
        $this->delete(route('admin.inventory.recipes.destroy', ['topping', $topping, $milk]))->assertStatus(303);
        $this->post(route('admin.orders.cancel', $cancelled->order_number), ['expected_status' => 'pending', 'reason' => 'Khách đổi ý'])->assertStatus(303);
        $this->assertSame(50000, $milk->refresh()->stock);
        $this->assertDatabaseMissing('inventory_movements', ['order_id' => $cancelled->id]);

        $this->actingAs($staff)->post(route('operations.orders.decide', $order->order_number), [
            'expected_status' => 'pending', 'decision' => 'confirmed',
        ])->assertStatus(303);
        $prepare = route('operations.orders.prepare', $order->order_number);
        $this->post($prepare, self::START)->assertStatus(303);
        $this->postJson($prepare, self::START)->assertConflict()->assertJsonValidationErrors('expected_status');
        $this->post($prepare, ['expected_status' => 'preparing', 'target_status' => 'ready'])->assertStatus(303);
        $this->actingAs($admin)->post(route('admin.orders.cancel', $order->order_number), ['expected_status' => 'ready', 'reason' => 'Khách xin hủy'])->assertStatus(303);
        $this->assertSame(18500, $milk->refresh()->stock);
        $this->assertSame(3850, $coffee->refresh()->stock);
        $this->assertDatabaseHas('inventory_movements', [
            'ingredient_id' => $milk->id, 'actor_id' => $staff->id, 'order_id' => $order->id,
            'kind' => 'consumption', 'quantity_change' => -31500, 'balance_after' => 18500,
        ]);
        $this->assertDatabaseCount('inventory_movements', 4);
        $this->get(route('admin.inventory.show', $milk))->assertOk()->assertSee($order->order_number)->assertSee($staff->name);
    }

    public function test_activation_checks_open_orders_opening_stock_and_only_sellable_options(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $customer = User::factory()->create();
        [$product, $size, $topping] = $this->catalog();
        $product->sizes()->create(['code' => 'l', 'label' => 'Lớn', 'price_vnd' => 45000, 'is_active' => false]);
        Topping::create(['code' => 'unused', 'name' => 'Chưa gắn', 'price_vnd' => 6000]);
        $ingredient = $this->ingredient('Sữa', 'ml');
        $legacy = $this->inventoryOrder($customer);
        $incomplete = $this->checkout($customer, $product, $size, $topping, 1);
        $this->actingAs($admin)->followingRedirects()->post(route('admin.inventory.enable'))
            ->assertOk()->assertSee('Chưa ghi tồn đầu kỳ cho')
            ->assertSee('Topping Kem')->assertSee('Còn 2 đơn chưa pha chế')->assertSee($legacy->order_number)
            ->assertSee($incomplete->order_number)->assertDontSee('Chưa gắn');

        $size->recipeIngredients()->attach($ingredient, ['quantity' => 1000]);
        $topping->recipeIngredients()->attach($ingredient, ['quantity' => 200]);
        $this->post(route('operations.orders.prepare', $legacy->order_number), self::START)->assertStatus(303);
        $this->assertSame(0, $ingredient->movements()->count());
        $this->post(route('admin.orders.cancel', $incomplete->order_number), ['expected_status' => 'pending', 'reason' => 'Xử lý trước khi bật kho'])->assertStatus(303);
        $this->initialize($ingredient, '0');
        $this->post(route('admin.inventory.enable'))->assertStatus(303);
        $this->get(route('admin.inventory.index'))->assertOk()->assertSee('Đang bật.');
    }

    public function test_missing_recipe_and_insufficient_ingredient_keep_stock_and_order_intact(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $staff = User::factory()->create(['role' => UserRole::Staff]);
        [$product, $size, $topping] = $this->catalog();
        $milk = $this->ingredient('Sữa', 'ml');
        $coffee = $this->ingredient('Cà phê', 'g');
        $size->recipeIngredients()->attach($milk, ['quantity' => 1000]);
        $topping->recipeIngredients()->attach($coffee, ['quantity' => 1751]);
        $this->actingAs($admin);
        $this->initialize($milk, '5');
        $this->initialize($coffee, '1,75');
        $this->post(route('admin.inventory.enable'))->assertStatus(303);

        $short = $this->checkout(User::factory()->create(), $product, $size, $topping, 1);
        $topping->recipeIngredients()->detach($coffee);
        $missing = $this->checkout(User::factory()->create(), $product, $size, $topping, 1);
        $topping->recipeIngredients()->attach($coffee, ['quantity' => 1000]);
        $unknownId = $coffee->id + 1000;
        $removed = $this->inventoryOrder(User::factory()->create(), ['quantities' => [$unknownId => 1000], 'missing' => []]);

        $this->actingAs($staff);
        foreach ([[$short, 'Cà phê thiếu 0,001 g'], [$missing, 'Đơn thiếu công thức đã chốt cho: Topping Kem']] as [$order, $message]) {
            $this->post(route('operations.orders.decide', $order->order_number), [
                'expected_status' => 'pending', 'decision' => 'confirmed',
            ])->assertStatus(303);
            $url = route('operations.orders.prepare', $order->order_number);
            $this->postJson($url, self::START)->assertConflict()->assertJsonValidationErrors('inventory');
            $this->post($url, self::START)->assertRedirectToRoute('operations.orders.show', $order->order_number);
            $this->get(route('operations.orders.show', $order->order_number))->assertOk()->assertSee($message);
            $this->assertSame(OrderStatus::Confirmed, $order->refresh()->status);
            $this->assertFalse($order->statusHistories()->where('to_status', 'preparing')->exists());
        }
        $this->assertSame(5000, $milk->refresh()->stock);
        $this->assertSame(1750, $coffee->refresh()->stock);
        $this->assertDatabaseCount('inventory_movements', 2);
        $this->postJson(route('operations.orders.prepare', $removed->order_number), self::START)
            ->assertConflict()->assertJsonPath('errors.inventory.0', "Không đủ nguyên liệu: Không tìm thấy nguyên liệu #{$unknownId} trong ảnh chụp công thức.");
        $this->assertSame(OrderStatus::Confirmed, $removed->refresh()->status);

        $this->actingAs($admin)->post(route('admin.inventory.record', $coffee), $this->movement($coffee, 'receipt', '0,001', 'Bổ sung nguyên liệu'))->assertStatus(303);
        $this->actingAs($staff)->post(route('operations.orders.prepare', $short->order_number), self::START)->assertStatus(303);
        $this->assertSame(0, $coffee->refresh()->stock);
        $this->assertSame(4000, $milk->refresh()->stock);
    }

    public function test_preparation_rolls_back_stock_and_ledger_when_order_history_fails(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $ingredient = $this->ingredient('Sữa', 'ml');
        $this->actingAs($admin);
        $this->initialize($ingredient, '10');
        $this->post(route('admin.inventory.enable'))->assertStatus(303);
        $order = $this->inventoryOrder(User::factory()->create(), ['quantities' => [$ingredient->id => 1000], 'missing' => []]);
        OrderStatusHistory::creating(static function (): never {
            throw new RuntimeException('Order history write failed');
        });
        $this->post(route('operations.orders.prepare', $order->order_number), self::START)->assertServerError();
        $this->assertSame(OrderStatus::Confirmed, $order->refresh()->status);
        $this->assertSame(10000, $ingredient->refresh()->stock);
        $this->assertSame(1, $ingredient->stock_version);
        $this->assertSame(1, $ingredient->movements()->count());
    }

    private function ingredient(string $name, string $unit): Ingredient
    {
        return Ingredient::create(['name' => $name, 'unit' => $unit, 'low_threshold' => 0]);
    }

    /** @return array{Product, ProductSize, Topping} */
    private function catalog(): array
    {
        $category = Category::create(['name' => 'Đồ uống', 'slug' => 'do-uong']);
        $product = $category->products()->create(['name' => 'Latte', 'slug' => 'latte']);
        $size = $product->sizes()->create(['code' => 'm', 'label' => 'Vừa', 'price_vnd' => 40000]);
        $topping = Topping::create(['code' => 'kem', 'name' => 'Kem', 'price_vnd' => 5000]);
        $product->toppings()->attach($topping);

        return [$product, $size, $topping];
    }

    private function initialize(Ingredient $ingredient, string $quantity): void
    {
        $this->post(route('admin.inventory.record', $ingredient), $this->movement($ingredient, 'count', $quantity, 'Tồn đầu kỳ'))->assertStatus(303);
    }

    private function movement(Ingredient $ingredient, string $kind, string $quantity, string $reason): array
    {
        return ['kind' => $kind, 'quantity' => $quantity, 'reason' => $reason, 'stock_version' => $ingredient->stock_version, 'entry_key' => Str::uuid()->toString()];
    }

    private function checkout(User $customer, Product $product, ProductSize $size, Topping $topping, int $cups): Order
    {
        app(ShoppingCart::class)->add($customer, $product->id, ['size_id' => $size->id, 'topping_ids' => [$topping->id]], $cups);
        $details = [
            'cart_version' => $customer->cart()->sole()->version, 'recipient_name' => 'Khách thử',
            'phone' => '0901234567', 'fulfillment_type' => 'pickup', 'method' => 'cod',
        ];
        $review = $this->actingAs($customer)->post(route('checkout.review'), $details)->assertOk();
        $key = $review->viewData('key');
        $this->post(route('checkout.store'), [
            ...$details, 'checkout_key' => $key, 'quote_hash' => $review->viewData('quote')['hash'],
            'accepted_total_vnd' => $review->viewData('quote')['order']['total_vnd'],
        ])->assertStatus(303);

        return $customer->orders()->where('checkout_key', $key)->sole();
    }
}
