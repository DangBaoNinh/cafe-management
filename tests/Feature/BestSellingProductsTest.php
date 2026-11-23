<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BestSellerFixture;
use Tests\TestCase;

class BestSellingProductsTest extends TestCase
{
    use BestSellerFixture;
    use RefreshDatabase;

    public function test_home_orders_public_best_sellers_by_quantity_then_id_without_counting_toppings_or_excluded_sales(): void
    {
        // At this UTC instant, the store has already rolled over to 22 November.
        $this->travelTo(CarbonImmutable::parse('2026-11-21 17:00:00', 'UTC'));
        $customer = User::factory()->create();
        $category = Category::create(['name' => 'Đồ uống', 'slug' => 'do-uong']);
        $hiddenCategory = Category::create(['name' => 'Danh mục ẩn', 'slug' => 'an', 'is_active' => false]);
        $mixed = $this->makeCatalogProduct($category, 'Cà phê pha nhiều size');
        $large = $mixed->sizes()->create(['code' => 'l', 'label' => 'Lớn', 'price_vnd' => 45000, 'is_active' => false]);
        $leader = $this->makeCatalogProduct($category, 'Cà phê đứng đầu');
        $tie = $this->makeCatalogProduct($category, 'Cà phê đồng hạng');
        $fourth = $this->makeCatalogProduct($category, 'Cà phê thứ tư');
        $fifth = $this->makeCatalogProduct($category, 'Cà phê thứ năm');
        $sixth = $this->makeCatalogProduct($category, 'Cà phê ngoài tốp');
        $hidden = $this->makeCatalogProduct($category, 'Món tắt', active: false);
        $inHiddenCategory = $this->makeCatalogProduct($hiddenCategory, 'Món trong danh mục tắt');
        $noActiveSize = $this->makeCatalogProduct($category, 'Món không còn size');
        $noActiveSize->sizes()->update(['is_active' => false]);

        $this->recordSale($customer, '2026-10-23 17:00:00', [[$mixed->sizes()->where('code', 'm')->sole(), 4]], withToppings: true);
        $this->recordSale($customer, '2026-11-22 16:59:59', [
            [$large, 3], [$leader->sizes()->sole(), 8], [$tie->sizes()->sole(), 7], [$fourth->sizes()->sole(), 3],
            [$fifth->sizes()->sole(), 2], [$sixth->sizes()->sole(), 2], [$hidden->sizes()->sole(), 100],
            [$inHiddenCategory->sizes()->sole(), 100], [$noActiveSize->sizes()->sole(), 100],
        ]);

        foreach (['2026-10-23 16:59:59', '2026-11-22 17:00:00'] as $outside) {
            $this->recordSale($customer, $outside, [[$sixth->sizes()->sole(), 100]]);
        }
        foreach ([['ready', 'paid'], ['cancelled', 'paid'], ['completed', 'pending'],
            ['completed', 'refunded'], ['completed', 'refund_pending'], ['completed', null]] as [$state, $payment]) {
            $this->recordSale($customer, '2026-11-20 12:00:00', [[$sixth->sizes()->sole(), 100]], $state, $payment);
        }

        $expected = [$leader, $mixed, $tie, $fourth, $fifth];
        $ids = array_map(fn ($product) => $product->id, $expected);
        $names = array_map(fn ($product) => $product->name, $expected);
        $response = $this->get(route('home'))->assertOk()->assertSeeInOrder(['Món bán chạy', ...$names])
            ->assertViewHas('bestSellers', fn ($products) => $products->modelKeys() === $ids)
            ->assertDontSee('Thông tin nội bộ UC28')->assertDontSee('Tên món tại thời điểm bán');
        foreach ($expected as $product) {
            $response->assertSee(route('menu.show', $product->slug));
        }
        $this->actingAs($customer)->get(route('home'))->assertOk()
            ->assertViewHas('bestSellers', fn ($products) => $products->modelKeys() === $ids);
    }

    public function test_home_hides_empty_ranking_and_displays_only_the_products_that_sold(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-21 17:00:00', 'UTC'));
        $category = Category::create(['name' => 'Đồ uống', 'slug' => 'do-uong']);
        $product = $this->makeCatalogProduct($category, 'Cà phê gợi ý');
        $this->get(route('home'))->assertOk()->assertDontSee('Món bán chạy')->assertSee('Gợi ý từ thực đơn');

        $this->recordSale(User::factory()->create(), '2026-11-21 17:00:00', [[$product->sizes()->sole(), 1]]);
        $this->get(route('home'))->assertOk()->assertSee('Món bán chạy')
            ->assertViewHas('bestSellers', fn ($products) => $products->modelKeys() === [$product->id]);
    }
}
