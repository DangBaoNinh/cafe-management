<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Livewire\DrinkCustomizer;
use App\Models\CartItem;
use App\Models\Category;
use App\Models\Product;
use App\Models\Topping;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ShoppingCartTest extends TestCase
{
    use RefreshDatabase;

    public function test_equivalent_toppings_merge_and_cart_prices_are_recomputed_on_the_server(): void
    {
        $user = User::factory()->create();
        $product = $this->product();
        $size = $product->sizes()->sole();
        $first = Topping::create(['code' => 'thach', 'name' => 'Thạch', 'price_vnd' => 5000]);
        $second = Topping::create(['code' => 'tran-chau', 'name' => 'Trân châu', 'price_vnd' => 7000]);
        $product->toppings()->attach([$first->id, $second->id]);
        $this->actingAs($user);

        $this->get(route('menu.show', $product->slug))->assertOk()
            ->assertSeeLivewire(DrinkCustomizer::class)->assertSee('Thêm vào giỏ');

        $this->add($product, [$second->id, $first->id], '2');
        $this->add($product, [$first->id, $second->id]);
        $cart = $user->cart()->sole();
        $merged = $cart->items()->sole();
        $this->assertSame(3, $merged->quantity);
        $this->assertEqualsCanonicalizing([$first->id, $second->id], $merged->toppings->modelKeys());
        $this->assertSame(2, $cart->version);

        $this->add($product, [$first->id], ice: 'less');
        $this->add($product, [$first->id, $second->id], ice: 'less');
        $this->assertDatabaseCount('carts', 1);
        $this->assertSame(3, $cart->items()->count());
        $this->get(route('cart.index'))->assertOk()->assertViewHas('version', 4)
            ->assertViewHas('subtotal', 243000)->assertSee('243.000 ₫')->assertSee('Tiêu chuẩn');

        $size->update(['price_vnd' => 40000]);
        $this->patch(route('cart.update', $merged), [
            'version' => '4', 'quantity' => '2', 'unit_price_vnd' => 1, 'subtotal' => 1,
        ])->assertRedirectToRoute('cart.index');
        $this->get(route('cart.index'))->assertOk()->assertViewHas('version', 5)
            ->assertViewHas('subtotal', 201000)->assertSee('52.000 ₫/ly');
    }

    public function test_a_stale_form_cannot_replace_an_update_or_remove_the_item(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $this->add($this->product());
        $cart = $user->cart()->sole();
        $item = $cart->items()->sole();
        $this->get(route('cart.index'))->assertViewHas('version', 1);

        $this->patch(route('cart.update', $item), ['version' => 1, 'quantity' => 4])
            ->assertRedirectToRoute('cart.index');
        $this->patch(route('cart.update', $item), ['version' => 1, 'quantity' => 2])
            ->assertRedirectToRoute('cart.index')->assertSessionHasErrors('version');
        $this->deleteJson(route('cart.destroy', $item), ['version' => 1])
            ->assertUnprocessable()->assertJsonValidationErrors('version');
        $this->assertSame(4, $item->refresh()->quantity);
        $this->assertSame(2, $cart->refresh()->version);

        $this->delete(route('cart.destroy', $item), ['version' => 2])
            ->assertRedirectToRoute('cart.index');
        $this->assertModelMissing($item);
        $this->delete(route('cart.destroy', $item), ['version' => 2])
            ->assertRedirectToRoute('cart.index')->assertSessionHasErrors('version');
        $this->get(route('cart.index'))->assertOk()->assertViewHas('version', 3)
            ->assertViewHas('subtotal', 0)->assertSee('Giỏ hàng của bạn đang trống.');
    }

    public function test_invalid_quantities_and_options_leave_the_cart_unchanged(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $product = $this->product();
        $unassigned = Topping::create(['code' => 'thach', 'name' => 'Thạch', 'price_vnd' => 5000]);
        $screen = Livewire::test(DrinkCustomizer::class, ['product' => $product]);

        foreach ([0, -1, '1.5', true, ['1'], CartItem::MAX_QUANTITY + 1] as $invalid) {
            $screen->set('quantity', $invalid)->call('addToCart')->assertHasErrors('quantity');
        }

        $screen->set('quantity', 1)->set('toppingIds', [$unassigned->id])
            ->call('addToCart')->assertHasErrors('topping_ids');
        $this->assertDatabaseCount('carts', 0);
        $this->add($product, quantity: CartItem::MAX_QUANTITY);
        $cart = $user->cart()->sole();
        $item = $cart->items()->sole();
        Livewire::test(DrinkCustomizer::class, ['product' => $product])
            ->call('addToCart')->assertHasErrors('quantity');
        $this->patchJson(route('cart.update', $item), ['version' => 1, 'quantity' => 0])
            ->assertUnprocessable()->assertJsonValidationErrors('quantity');
        $this->deleteJson(route('cart.destroy', $item))->assertUnprocessable()->assertJsonValidationErrors('version');
        $this->assertSame(CartItem::MAX_QUANTITY, $item->refresh()->quantity);
        $this->assertSame(1, $cart->refresh()->version);
    }

    public function test_guest_is_redirected_and_other_roles_or_customers_cannot_change_a_foreign_cart(): void
    {
        $product = $this->product();
        $this->get(route('cart.index'))->assertRedirectToRoute('login');
        Livewire::test(DrinkCustomizer::class, ['product' => $product])
            ->call('addToCart')->assertRedirect(route('login'));
        $this->assertSame(route('menu.show', $product->slug), session('url.intended'));
        $this->assertDatabaseCount('carts', 0);

        foreach ([UserRole::Staff, UserRole::Admin] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]));
            $this->get(route('cart.index'))->assertForbidden();
            Livewire::test(DrinkCustomizer::class, ['product' => $product])
                ->call('addToCart')->assertForbidden();
        }

        $owner = User::factory()->create();
        $this->actingAs($owner);
        $this->add($product);
        $item = $owner->cart()->sole()->items()->sole();

        $this->actingAs(User::factory()->create());
        $this->add($product);
        $this->get(route('cart.index'))->assertOk()->assertViewHas('lines', fn ($lines) => count($lines) === 1
            && $lines[0]['item']->id !== $item->id);
        $this->patch(route('cart.update', $item), ['version' => 1, 'quantity' => 2])->assertNotFound();
        $this->delete(route('cart.destroy', $item), ['version' => 1])->assertNotFound();
        $this->assertSame(1, $item->refresh()->quantity);
        $this->assertSame(1, $owner->cart()->sole()->version);
    }

    public function test_unavailable_lines_are_flagged_and_can_be_removed(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $product = $this->product();
        $topping = Topping::create(['code' => 'thach', 'name' => 'Thạch', 'price_vnd' => 5000]);
        $product->toppings()->attach($topping);
        $this->add($product, [$topping->id]);
        $cart = $user->cart()->sole();
        $item = $cart->items()->sole();
        $screen = Livewire::test(DrinkCustomizer::class, ['product' => $product])
            ->set('toppingIds', [$topping->id]);
        $product->toppings()->detach($topping);

        $screen->call('addToCart')->assertHasErrors('topping_ids');
        $this->get(route('cart.index'))->assertOk()->assertViewHas('subtotal', 0)
            ->assertSee('Món hoặc tùy chọn đã ngừng bán hoặc thay đổi.');
        $this->patchJson(route('cart.update', $item), ['version' => 1, 'quantity' => 3])
            ->assertUnprocessable()->assertJsonValidationErrors('topping_ids');
        $this->assertSame(1, $cart->refresh()->version);
        $this->delete(route('cart.destroy', $item), ['version' => 1])->assertRedirectToRoute('cart.index');
        $this->assertDatabaseCount('cart_item_toppings', 0);
    }

    private function product(): Product
    {
        $category = Category::create(['slug' => 'ca-phe', 'name' => 'Cà phê']);
        $product = $category->products()->create(['slug' => 'ca-phe-sua', 'name' => 'Cà phê sữa', 'allows_ice' => true]);
        $product->sizes()->create(['code' => 'm', 'label' => 'Cỡ vừa', 'price_vnd' => 38000]);

        return $product;
    }

    private function add(Product $product, array $toppings = [], int|string $quantity = 1, string $ice = 'standard'): void
    {
        Livewire::test(DrinkCustomizer::class, ['product' => $product])
            ->set('toppingIds', $toppings)->set('quantity', $quantity)->set('iceLevel', $ice)
            ->call('addToCart')->assertHasNoErrors()->assertRedirect(route('cart.index'));
    }
}
