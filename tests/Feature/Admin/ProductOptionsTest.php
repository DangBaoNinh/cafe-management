<?php

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Models\Category;
use App\Models\Product;
use App\Models\Topping;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductOptionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_update_and_stop_selling_a_size(): void
    {
        $this->signInAsAdmin();
        $product = $this->product();
        $options = route('admin.products.options', $product);
        $fields = $this->sizeFields();

        $this->get($options)->assertOk()->assertSee('Sản phẩm chưa có size.');
        $this->get(route('admin.products.sizes.create', $product))->assertOk()->assertSee('Lưu size');
        $this->post(route('admin.products.sizes.store', $product), $fields)
            ->assertRedirect($options)->assertSessionHasNoErrors();

        $size = $product->activeSizes()->sole();
        $this->assertSame('m', $size->code);
        $this->assertSame(42000, $size->price_vnd);
        $this->get($options)->assertOk()->assertSee('42.000');
        $this->get(route('admin.products.sizes.edit', [$product, $size]))->assertOk()->assertSee('Chỉnh sửa size');

        $update = route('admin.products.sizes.update', [$product, $size]);
        $this->patch($update, [...$fields, 'is_active' => '0', 'price_vnd' => '0'])
            ->assertRedirect($options)->assertSessionHasNoErrors();
        $this->assertFalse($product->activeSizes()->exists());
        $this->assertFalse($size->refresh()->is_active);
        $this->assertSame(0, $size->price_vnd);
        $this->get($options)->assertOk()->assertSee('Ngừng bán');
        $this->delete($update)->assertMethodNotAllowed();
        $this->assertModelExists($size);
    }

    public function test_size_codes_are_unique_per_product_and_binding_is_scoped(): void
    {
        $this->signInAsAdmin();
        $product = $this->product();
        $other = $this->product('tra-dao');
        $fields = $this->sizeFields();

        foreach ([$product, $other] as $item) {
            $this->post(route('admin.products.sizes.store', $item), $fields)->assertSessionHasNoErrors();
        }

        $this->post(route('admin.products.sizes.store', $product), [...$fields, 'code' => 'm'])
            ->assertSessionHasErrors('code');
        $anotherSize = $product->sizes()->create([...$fields, 'code' => 'l']);
        $this->patch(route('admin.products.sizes.update', [$product, $anotherSize]), $fields)
            ->assertSessionHasErrors('code');

        $size = $product->sizes()->where('code', 'm')->sole();
        $this->get(route('admin.products.sizes.edit', [$other, $size]))->assertNotFound();
        $this->patch(route('admin.products.sizes.update', [$other, $size]), [...$fields, 'is_active' => '0'])
            ->assertNotFound();
        $this->assertTrue($size->refresh()->is_active);
        $this->assertDatabaseCount('product_sizes', 3);
    }

    public function test_admin_can_manage_toppings_and_only_active_attached_toppings_are_selectable(): void
    {
        $this->signInAsAdmin();
        $product = $this->product();
        $other = $this->product('tra-dao');
        $index = route('admin.toppings.index');
        $options = route('admin.products.options', $product);
        $fields = $this->toppingFields();

        $this->get(route('admin.toppings.create'))->assertOk()->assertSee('Lưu topping');
        $this->post(route('admin.toppings.store'), $fields)->assertRedirect($index)->assertSessionHasNoErrors();
        $this->get($index)->assertOk()->assertSee('Trân châu')->assertSee('6.000');
        $topping = Topping::sole();
        $this->get(route('admin.toppings.edit', $topping))->assertOk()->assertSee('Chỉnh sửa topping');
        $this->get($options)->assertOk()->assertSee('Trân châu');

        $attach = route('admin.products.toppings.store', $product);
        $this->post($attach, ['topping_id' => $topping->id])->assertRedirect($options)->assertSessionHasNoErrors();
        $this->post($attach, ['topping_id' => $topping->id])->assertSessionHasErrors('topping_id');
        $this->post(route('admin.products.toppings.store', $other), ['topping_id' => $topping->id])->assertSessionHasNoErrors();
        $this->assertTrue($product->activeToppings()->sole()->is($topping));

        $update = route('admin.toppings.update', $topping);
        $this->patch($update, [...$fields, 'is_active' => '0', 'price_vnd' => '7500'])
            ->assertRedirect($index)->assertSessionHasNoErrors();
        $this->assertSame(7500, $topping->refresh()->price_vnd);
        $this->assertFalse($product->activeToppings()->exists());
        $this->assertFalse($other->activeToppings()->exists());
        $this->assertTrue($product->toppings()->sole()->is($topping));
        $this->get($options)->assertOk()->assertSee('Ngừng bán')->assertSee('Hiện chưa có topping đang bán để gắn thêm.');

        $detach = route('admin.products.toppings.destroy', [$product, $topping]);
        $this->delete($detach)->assertRedirect($options);
        $this->delete($detach)->assertNotFound();
        $this->get($options)->assertOk()->assertDontSee('Trân châu');
        $this->post($attach, ['topping_id' => $topping->id])->assertSessionHasErrors('topping_id');
        $this->post($attach, ['topping_id' => 999])->assertSessionHasErrors('topping_id');
        $this->assertFalse($product->toppings()->exists());
        $this->assertTrue($other->toppings()->sole()->is($topping));
        $this->delete($update)->assertMethodNotAllowed();
        $this->assertModelExists($topping);
    }

    public function test_invalid_prices_and_topping_codes_do_not_write(): void
    {
        $this->signInAsAdmin();
        $product = $this->product();
        $size = route('admin.products.sizes.store', $product);
        $topping = route('admin.toppings.store');

        foreach (['-1', '12500.5', '4294967296'] as $price) {
            $this->post($size, [...$this->sizeFields(), 'price_vnd' => $price])->assertSessionHasErrors('price_vnd');
            $this->post($topping, [...$this->toppingFields(), 'price_vnd' => $price])->assertSessionHasErrors('price_vnd');
        }

        Topping::create($this->toppingFields());
        $this->post($topping, [...$this->toppingFields(), 'code' => 'TRAN-CHAU'])->assertSessionHasErrors('code');
        $this->post($size, [...$this->sizeFields(), 'code' => 'Cỡ lớn'])->assertSessionHasErrors('code');
        $this->assertDatabaseCount('product_sizes', 0);
        $this->assertDatabaseCount('toppings', 1);
    }

    public function test_product_options_can_only_be_managed_by_admins(): void
    {
        $product = $this->product();
        $topping = Topping::create($this->toppingFields());
        $product->toppings()->attach($topping);
        $options = route('admin.products.options', $product);
        $size = route('admin.products.sizes.store', $product);
        $attach = route('admin.products.toppings.store', $product);
        $detach = route('admin.products.toppings.destroy', [$product, $topping]);
        $this->get($options)->assertRedirect(route('login'));
        $this->post($size, $this->sizeFields())->assertRedirect(route('login'));

        foreach ([UserRole::Staff, UserRole::Customer] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]));
            $this->get($options)->assertForbidden();
            $this->get(route('admin.toppings.index'))->assertForbidden();
            $this->post($size, $this->sizeFields())->assertForbidden();
            $this->post($attach, ['topping_id' => $topping->id])->assertForbidden();
            $this->delete($detach)->assertForbidden();
            $this->patch(route('admin.toppings.update', $topping), [
                ...$this->toppingFields(), 'is_active' => '0',
            ])->assertForbidden();
        }

        $this->assertTrue($topping->refresh()->is_active);
        $this->assertDatabaseCount('product_sizes', 0);
        $this->assertDatabaseCount('product_toppings', 1);
    }

    private function signInAsAdmin(): void
    {
        $this->actingAs(User::factory()->create(['role' => UserRole::Admin]));
    }

    private function product(string $slug = 'ca-phe-sua'): Product
    {
        $category = Category::firstOrCreate(['slug' => 'do-uong'], ['name' => 'Đồ uống']);

        return $category->products()->create(['name' => 'Món thử', 'slug' => $slug]);
    }

    private function sizeFields(): array
    {
        return ['code' => 'M', 'label' => 'Vừa', 'price_vnd' => '42000', 'is_active' => '1'];
    }

    private function toppingFields(): array
    {
        return ['code' => 'tran-chau', 'name' => 'Trân châu', 'price_vnd' => '6000', 'is_active' => '1'];
    }
}
