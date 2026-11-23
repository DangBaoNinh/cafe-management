<?php

namespace Tests\Integration\MySql;

use App\Models\Cart;
use App\Models\Category;
use App\Models\Topping;
use App\Models\User;
use App\Services\ShoppingCart;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class ShoppingCartTest extends MySqlTestCase
{
    public function test_cart_writes_respect_constraints_and_rollback_on_failure(): void
    {
        DB::setDefaultConnection($this->primary->getName());
        $this->primary->beginTransaction();

        try {
            $slug = Str::uuid()->toString();
            $user = User::factory()->create();
            $category = Category::create(['slug' => $slug, 'name' => 'Cà phê thử']);
            $product = $category->products()->create(['slug' => $slug, 'name' => 'Món thử']);
            $size = $product->sizes()->create(['code' => 'm', 'label' => 'Vừa', 'price_vnd' => 47000]);
            $topping = Topping::create(['code' => $slug, 'name' => 'Thạch', 'price_vnd' => 6000]);
            $product->toppings()->attach($topping);
            $selection = ['size_id' => $size->id, 'topping_ids' => [$topping->id]];
            $service = app(ShoppingCart::class);

            $service->add($user, $product->id, $selection, 1);
            $service->add($user, $product->id, $selection, 2);
            $cart = $user->cart()->sole();
            $item = $cart->items()->sole();
            $this->assertSame(2, $cart->version);
            $this->assertSame(3, $item->quantity);
            $this->assertSame(159000, $service->view($user)['subtotal']);

            try {
                $this->primary->table('cart_items')->where('id', $item->id)->update(['quantity' => 0]);
                $this->fail('Expected MySQL to reject a zero quantity.');
            } catch (QueryException $error) {
                $this->assertSame(3819, $error->errorInfo[1]);
            }

            try {
                $user->cart()->create();
                $this->fail('Expected MySQL to reject a second cart for this customer.');
            } catch (QueryException $error) {
                $this->assertSame(1062, $error->errorInfo[1]);
            }

            $service->update($user, (string) $item->id, 2, 4);
            try {
                $service->remove($user, (string) $item->id, 2);
                $this->fail('Expected the old cart version to be rejected.');
            } catch (ValidationException $error) {
                $this->assertArrayHasKey('version', $error->errors());
            }

            $failure = new RuntimeException('Simulated failure at version update');
            Cart::updating(static function () use ($failure): never {
                throw $failure;
            });
            try {
                $service->remove($user, (string) $item->id, 3);
                $this->fail('Expected the transaction to fail after removing the line.');
            } catch (RuntimeException $error) {
                $this->assertSame($failure, $error);
            }

            $this->assertSame(3, $cart->refresh()->version);
            $this->assertSame(4, $item->refresh()->quantity);
            $this->assertSame([$topping->id], $item->toppings->modelKeys());
        } finally {
            $this->primary->rollBack();
        }
    }
}
