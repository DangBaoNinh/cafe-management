<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CatalogSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_combined_filters_use_the_minimum_active_price_and_only_public_products(): void
    {
        $coffee = Category::create(['name' => 'Cà phê', 'slug' => 'ca-phe']);
        $tea = Category::create(['name' => 'Trà', 'slug' => 'tra']);
        $hidden = Category::create(['name' => 'Ẩn', 'slug' => 'an', 'is_active' => false]);
        $low = $this->product($coffee, 'ca-phe-thap', 'Cà phê sữa nguyên bản', 35000);
        $high = $this->product($coffee, 'ca-phe-cao', 'Cà phê sữa ủ lạnh', 45000);
        $cheap = $this->product($coffee, 'ca-phe-re', 'Cà phê sữa mini', 24000);
        $cheap->sizes()->create(['code' => 'l', 'label' => 'Lớn', 'price_vnd' => 40000]);
        $high->sizes()->create(['code' => 's', 'label' => 'Nhỏ', 'price_vnd' => 1000, 'is_active' => false]);
        $this->product($coffee, 'ca-phe-den', 'Cà phê đen', 40000);
        $this->product($tea, 'tra-ca-phe', 'Trà vị cà phê sữa', 40000);
        $this->product($coffee, 'ca-phe-tat', $low->name, 39000, false);
        $this->product($hidden, 'ca-phe-an', $high->name, 45000);

        $filters = ['q' => 'cà phê sữa', 'category' => $coffee->slug, 'min_price' => 35000, 'max_price' => 45000];
        $this->get(route('menu.index', [...$filters, 'q' => '  cà phê sữa  ', 'sort' => 'price_desc']))
            ->assertOk()->assertSeeInOrder([$high->slug, $low->slug])->assertDontSee($cheap->slug)
            ->assertDontSee('ca-phe-den')->assertDontSee('tra-ca-phe')->assertDontSee('ca-phe-tat')->assertDontSee('ca-phe-an');
        $this->get(route('menu.index', [...$filters, 'sort' => 'price_asc']))
            ->assertOk()->assertSeeInOrder([$low->slug, $high->slug]);

        $this->product($coffee, 'ca-phe-mau', 'Cà phê sữa dùng thử', 0);
        $this->get(route('menu.index', ['q' => 'sữa dùng thử', 'max_price' => 0]))
            ->assertOk()->assertSee('ca-phe-mau')->assertSee('Từ 0 ₫');
    }

    public function test_search_treats_wildcard_characters_as_literal_text(): void
    {
        $category = Category::create(['name' => 'Cà phê', 'slug' => 'ca-phe']);
        $this->product($category, 'matching-drink', 'Cà phê 100%_!', 40000);
        $this->product($category, 'different-drink', 'Cà phê 100g A!', 40000);

        $this->get(route('menu.index', ['q' => '%_!']))->assertOk()
            ->assertSee('matching-drink')->assertDontSee('different-drink');
        $this->get(route('menu.index', ['q' => 'không tồn tại']))->assertOk()
            ->assertSee('Không tìm thấy món phù hợp.')->assertSee('Xóa tất cả bộ lọc');
    }

    public function test_pagination_detail_and_clear_actions_keep_only_the_applicable_filters(): void
    {
        $category = Category::create(['name' => 'Cà phê', 'slug' => 'ca-phe']);
        $other = Category::create(['name' => 'Trà', 'slug' => 'tra']);
        for ($number = 1; $number <= 13; $number++) {
            $this->product($category, sprintf('ca-phe-%02d', $number), sprintf('Cà phê %02d', $number), 39000);
        }

        $filters = ['q' => 'Cà phê', 'category' => $category->slug, 'min_price' => '30000', 'max_price' => '40000', 'sort' => 'price_asc'];
        $pageTwo = route('menu.index', [...$filters, 'page' => 2]);
        $detail = route('menu.show', ['slug' => 'ca-phe-13', ...$filters, 'page' => 2]);

        $response = $this->get(route('menu.index', [...$filters, 'ignored' => 'unused']));
        $response->assertOk()->assertSee($pageTwo)->assertDontSee('ignored=');
        $this->get($pageTwo)->assertOk()->assertSee($detail)
            ->assertSee(route('menu.index', Arr::except($filters, ['q'])))
            ->assertSee(route('menu.index', Arr::except($filters, ['category'])))
            ->assertSee(route('menu.index', [...$filters, 'category' => $other->slug]))
            ->assertSee('Xóa tất cả bộ lọc');
        $this->get($detail)->assertOk()->assertSee($pageTwo);
        $this->get(route('menu.index', [...$filters, 'page' => 4]))->assertOk()
            ->assertSee('Trang này không có món.')->assertSee(route('menu.index', [...$filters, 'page' => 1]));
    }

    public function test_invalid_filter_queries_are_rejected_with_recoverable_errors(): void
    {
        foreach ([
            ['q' => ['cà phê']],
            ['q' => str_repeat('x', 101)],
            ['min_price' => '-1'],
            ['max_price' => '30000.5'],
            ['max_price' => '4294967296'],
            ['min_price' => ['0'], 'max_price' => '30000'],
            ['min_price' => '40000', 'max_price' => '30000'],
            ['sort' => 'unknown'],
            ['sort' => ['price_asc']],
            ['page' => '2147483648'],
            ['page' => ['2']],
        ] as $invalid) {
            $this->getJson(route('menu.index', $invalid))->assertUnprocessable();
        }

        $this->get(route('menu.index', ['min_price' => '40000', 'max_price' => '30000']))
            ->assertUnprocessable()->assertSee('Giá đến phải lớn hơn hoặc bằng giá từ.')
            ->assertSee('40000')->assertSee('30000');
    }

    public function test_search_only_reads_database_for_a_signed_in_customer(): void
    {
        $category = Category::create(['name' => 'Cà phê', 'slug' => 'ca-phe']);
        $this->product($category, 'ca-phe-sua', 'Cà phê sữa', 40000);
        $this->actingAs(User::factory()->create());

        DB::enableQueryLog();
        try {
            $this->get(route('menu.index', ['q' => 'Cà phê']))->assertOk()->assertSee('ca-phe-sua');
            $queries = DB::getQueryLog();
            $this->assertNotEmpty($queries);
            foreach ($queries as $statement) {
                $this->assertStringStartsWith('select', strtolower(ltrim($statement['query'])));
            }
        } finally {
            DB::disableQueryLog();
            DB::flushQueryLog();
        }
    }

    private function product(Category $category, string $slug, string $name, int $price, bool $active = true): Product
    {
        $product = $category->products()->create(['slug' => $slug, 'name' => $name, 'is_active' => $active]);
        $product->sizes()->create(['code' => 'm', 'label' => 'Vừa', 'price_vnd' => $price]);

        return $product;
    }
}
