<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_can_browse_the_menu_and_read_product_details(): void
    {
        $coffee = Category::create(['name' => 'Cà phê', 'slug' => 'ca-phe', 'sort_order' => 10]);
        Category::create(['name' => 'Trà', 'slug' => 'tra', 'sort_order' => 1]);
        $description = "Cà phê rang vừa.\n<strong>Vị đậm</strong>";
        $product = $this->product($coffee, 'ca-phe-sua', [
            'name' => 'Cà phê sữa đậm',
            'description' => $description,
            'image_path' => 'products/milk-coffee.png',
        ]);
        $this->addSize($product, 's', 'Cỡ nhỏ', 35000);
        $this->addSize($product, 'l', 'Cỡ lớn', 48000);
        $this->addSize($product, 'mini', 'Cỡ đã ngừng bán', 10000, false);
        $index = route('menu.index');
        $show = route('menu.show', $product->slug);
        $imageUrl = Storage::disk('public')->url($product->image_path);

        $this->get(route('home'))->assertOk()->assertSee($index)->assertSee('Xem thực đơn');
        $this->get($index)->assertOk()
            ->assertSeeInOrder(['Tất cả', 'Trà', 'Cà phê', 'Cà phê sữa đậm'])
            ->assertSee($imageUrl)->assertSee($show)->assertSee('Từ 35.000 ₫')->assertDontSee('10.000');
        $this->get($show)->assertOk()->assertSee($imageUrl)->assertSee('Từ 35.000 ₫')
            ->assertSeeInOrder(['Cỡ nhỏ', '35.000', 'Cỡ lớn', '48.000'])
            ->assertDontSee('Cỡ đã ngừng bán')->assertSee($description)->assertDontSee($description, false);
        $this->assertGuest();

        $this->actingAs(User::factory()->create())->get($show)->assertOk()->assertSee($product->name);
    }

    public function test_menu_and_detail_hide_ineligible_products(): void
    {
        $coffee = Category::create(['name' => 'Đồ uống', 'slug' => 'do-uong']);
        $hiddenCategory = Category::create(['name' => 'Danh mục nội bộ', 'slug' => 'noi-bo', 'is_active' => false]);
        $hidden = $this->product($coffee, 'draft-product', ['is_active' => false]);
        $inHiddenCategory = $this->product($hiddenCategory, 'hidden-category-product');
        $withoutSizes = $this->product($coffee, 'product-without-sizes');
        $onlyInactiveSizes = $this->product($coffee, 'product-with-inactive-sizes');
        $visible = $this->product($coffee, 'eligible-product');

        $this->addSize($hidden, 'm', 'Vừa', 1000);
        $this->addSize($inHiddenCategory, 'm', 'Vừa', 2000);
        $this->addSize($onlyInactiveSizes, 'm', 'Vừa', 3000, false);
        $this->addSize($visible, 'm', 'Vừa', 0);

        $menu = $this->get(route('menu.index'))->assertOk()->assertSee('eligible-product')->assertSee('Từ 0 ₫');
        $menu->assertDontSee('Danh mục nội bộ');
        foreach ([$hidden, $inHiddenCategory, $withoutSizes, $onlyInactiveSizes] as $ineligible) {
            $menu->assertDontSee($ineligible->slug);
            $this->get(route('menu.show', $ineligible->slug))->assertNotFound();
        }

        $this->get(route('menu.show', 'missing-slug'))->assertNotFound();
        $this->get(route('menu.index', ['category' => $hiddenCategory->slug]))->assertNotFound();
        $this->get(route('menu.index', ['category' => ['array']]))->assertNotFound();
    }

    public function test_prices_and_visibility_follow_current_size_and_category_state(): void
    {
        $category = Category::create(['name' => 'Trà', 'slug' => 'tra']);
        $product = $this->product($category, 'tra-dao');
        $this->addSize($product, 'm', 'Vừa', 36000);
        $this->addSize($product, 'l', 'Lớn', 48000);
        $small = $product->sizes()->where('code', 'm')->sole();
        $index = route('menu.index');
        $show = route('menu.show', $product->slug);

        $small->update(['price_vnd' => 39000]);
        $this->get($index)->assertSee('Từ 39.000 ₫');
        $this->get($show)->assertSee('Từ 39.000 ₫');

        $small->update(['is_active' => false]);
        $this->get($index)->assertSee('Từ 48.000 ₫')->assertDontSee('39.000');
        $this->get($show)->assertSee('Từ 48.000 ₫')->assertDontSee('39.000');

        $category->update(['is_active' => false]);
        $this->get($index)->assertSee('Thực đơn hiện chưa có món đang bán.')->assertDontSee($product->slug);
        $this->get($show)->assertNotFound();
    }

    public function test_category_filter_and_pagination_are_preserved_on_return_from_a_product(): void
    {
        $tea = Category::create(['name' => 'Trà trái cây', 'slug' => 'tra']);
        $coffee = Category::create(['name' => 'Cà phê', 'slug' => 'ca-phe']);
        $empty = Category::create(['name' => 'Đồ uống theo mùa', 'slug' => 'theo-mua']);

        for ($number = 1; $number <= 13; $number++) {
            $teaProduct = $this->product($tea, sprintf('tra-dao-%02d', $number));
            $this->addSize($teaProduct, 'm', 'Vừa', 42000);
        }

        $this->addSize($this->product($coffee, 'coffee-special'), 's', 'Nhỏ', 39000);

        $categoryUrl = route('menu.index', ['category' => $tea->slug]);
        $pageTwo = route('menu.index', ['category' => $tea->slug, 'page' => 2]);
        $productPage = route('menu.show', ['slug' => 'tra-dao-13', 'category' => $tea->slug, 'page' => 2]);
        $this->get($categoryUrl)->assertOk()->assertSee('tra-dao-01')->assertSee('tra-dao-12')
            ->assertDontSee('tra-dao-13')->assertDontSee('coffee-special')->assertSee($pageTwo)
            ->assertSee('Trang sau')->assertSee('Điều hướng phân trang');
        $this->get($pageTwo)->assertOk()->assertSee($productPage)->assertDontSee('tra-dao-12');
        $this->get($productPage)->assertOk()->assertSee($pageTwo);
        $this->get(route('menu.index', ['category' => $empty->slug]))
            ->assertOk()->assertSee('Danh mục này chưa có món đang bán.');
        $this->get(route('menu.index', ['category' => $tea->slug, 'page' => 3]))
            ->assertOk()->assertSee('Trang này không có món.')->assertSee('Về trang đầu');
    }

    private function product(Category $category, string $slug, array $attributes = []): Product
    {
        return $category->products()->create(['slug' => $slug, 'name' => $slug, ...$attributes]);
    }

    private function addSize(Product $product, string $code, string $label, int $price, bool $active = true): void
    {
        $product->sizes()->create(['code' => $code, 'label' => $label, 'price_vnd' => $price, 'is_active' => $active]);
    }
}
