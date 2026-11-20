<?php

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_an_active_admin_can_manage_categories(): void
    {
        $category = Category::create($this->category());
        $index = route('admin.categories.index');
        $store = route('admin.categories.store');
        $update = route('admin.categories.update', $category);

        $this->get($index)->assertRedirect(route('login'));
        $this->post($store, $this->category())->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create(['role' => UserRole::Staff]));
        $this->get($index)->assertForbidden();
        $this->post($store, $this->category())->assertForbidden();
        $this->patch($update, $this->category(['is_active' => '0']))->assertForbidden();

        $this->assertTrue($category->fresh()->is_active);
        $this->assertDatabaseCount('categories', 1);
    }

    public function test_an_admin_can_create_edit_and_reorder_categories(): void
    {
        $this->signInAsAdmin();
        $this->get(route('admin.categories.create'))->assertOk()->assertSee('Lưu danh mục');

        $first = Category::create($this->category(['name' => 'Cà phê', 'sort_order' => 10]));
        $second = $this->category(['name' => 'Trà', 'slug' => 'tra', 'sort_order' => 20]);
        $this->post(route('admin.categories.store'), $second)
            ->assertRedirect(route('admin.categories.index'))->assertSessionHasNoErrors();

        $tea = Category::where('slug', 'tra')->sole();
        $this->get(route('admin.categories.edit', $tea))->assertOk()->assertSee('Chỉnh sửa danh mục');
        $this->get(route('admin.categories.index'))->assertOk()->assertSeeInOrder(['Cà phê', 'Trà']);

        $this->patch(route('admin.categories.update', $tea), [
            ...$second, 'name' => 'Trà trái cây', 'sort_order' => 5,
        ])->assertRedirect(route('admin.categories.index'))->assertSessionHasNoErrors();

        $this->get(route('admin.categories.index'))->assertOk()->assertSeeInOrder(['Trà trái cây', 'Cà phê']);
        $this->assertSame(5, $tea->fresh()->sort_order);
        $this->assertSame(10, $first->fresh()->sort_order);
    }

    public function test_a_duplicate_slug_or_negative_order_does_not_write(): void
    {
        $this->signInAsAdmin();
        Category::create($this->category());
        $other = Category::create($this->category(['slug' => 'tra']));

        $this->post(route('admin.categories.store'), $this->category(['sort_order' => -1]))
            ->assertSessionHasErrors(['slug', 'sort_order']);
        $this->patch(route('admin.categories.update', $other), $this->category())
            ->assertSessionHasErrors('slug');
        $this->post(route('admin.categories.store'), $this->category(['slug' => 'Không Hợp Lệ']))
            ->assertSessionHasErrors('slug');

        $this->assertSame('tra', $other->fresh()->slug);
        $this->assertDatabaseCount('categories', 2);
    }

    public function test_disabling_a_category_hides_its_products_and_preserves_the_records(): void
    {
        $this->signInAsAdmin();
        $category = Category::create($this->category());
        $other = Category::create($this->category(['slug' => 'tra']));
        $product = $category->products()->create(['name' => 'Cà phê sữa', 'slug' => 'ca-phe-sua', 'is_active' => true]);
        $unaffected = $other->products()->create(['name' => 'Trà đào', 'slug' => 'tra-dao', 'is_active' => true]);
        $url = route('admin.categories.update', $category);

        $this->assertTrue(Product::inActiveCategory()->whereKey($product->id)->exists());
        $this->patch($url, $this->category(['is_active' => '0']))->assertSessionHasNoErrors();

        $this->assertFalse(Category::active()->whereKey($category->id)->exists());
        $this->assertFalse(Product::inActiveCategory()->whereKey($product->id)->exists());
        $this->assertTrue(Product::inActiveCategory()->whereKey($unaffected->id)->exists());
        $this->assertDatabaseHas('products', ['id' => $product->id, 'category_id' => $category->id, 'is_active' => true]);
        $this->delete($url)->assertMethodNotAllowed();
        $this->assertModelExists($category);

        $this->patch($url, $this->category())->assertSessionHasNoErrors();
        $this->assertTrue(Product::inActiveCategory()->whereKey($product->id)->exists());
    }

    private function signInAsAdmin(): void
    {
        $this->actingAs(User::factory()->create(['role' => UserRole::Admin]));
    }

    private function category(array $overrides = []): array
    {
        return array_replace([
            'name' => 'Danh mục thử',
            'slug' => 'ca-phe',
            'sort_order' => 0,
            'is_active' => '1',
        ], $overrides);
    }
}
