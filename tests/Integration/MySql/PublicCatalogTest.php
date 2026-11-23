<?php

namespace Tests\Integration\MySql;

use App\Models\Category;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PublicCatalogTest extends MySqlTestCase
{
    public function test_mysql_menu_aggregates_only_prices_of_active_sizes(): void
    {
        DB::setDefaultConnection($this->primary->getName());
        $this->primary->beginTransaction();

        try {
            $slug = 'catalog-'.Str::uuid();
            $category = Category::create(['slug' => $slug, 'name' => 'Đồ uống thử']);
            $product = $category->products()->create(['slug' => $slug, 'name' => 'Món đang bán']);
            $unavailable = $category->products()->create(['slug' => 'draft-'.$slug, 'name' => 'Món thiếu size']);
            $product->sizes()->createMany([
                ['code' => 's', 'label' => 'Nhỏ đã ngừng bán', 'price_vnd' => 1000, 'is_active' => false],
                ['code' => 'm', 'label' => 'Vừa', 'price_vnd' => 39000],
                ['code' => 'l', 'label' => 'Lớn', 'price_vnd' => 46000],
            ]);

            $this->get(route('menu.index', ['category' => $slug]))->assertOk()->assertSee('Món đang bán')
                ->assertSee('Từ 39.000 ₫')->assertDontSee('Món thiếu size');
            $this->get(route('menu.show', $slug))->assertOk()->assertSee('Từ 39.000 ₫')->assertDontSee('Nhỏ đã ngừng bán');
            $this->get(route('menu.show', $unavailable->slug))->assertNotFound();

            $category->update(['is_active' => false]);
            $this->get(route('menu.show', $slug))->assertNotFound();
        } finally {
            $this->primary->rollBack();
        }
    }

    public function test_mysql_search_binds_keywords_and_filters_by_starting_price(): void
    {
        DB::setDefaultConnection($this->primary->getName());
        $this->primary->beginTransaction();

        try {
            $slug = 'search-'.Str::uuid();
            $category = Category::create(['slug' => $slug, 'name' => 'Cà phê thử']);
            $eligible = $category->products()->create(['slug' => $slug, 'name' => 'Cà phê sữa 100%']);
            $cheaper = $category->products()->create(['slug' => 'cheap-'.$slug, 'name' => 'Cà phê sữa 50%']);
            $eligible->sizes()->createMany([
                ['code' => 's', 'label' => 'Nhỏ', 'price_vnd' => 19000, 'is_active' => false],
                ['code' => 'm', 'label' => 'Vừa', 'price_vnd' => 40000],
            ]);
            $cheaper->sizes()->createMany([
                ['code' => 's', 'label' => 'Nhỏ', 'price_vnd' => 30000],
                ['code' => 'm', 'label' => 'Vừa', 'price_vnd' => 40000],
            ]);

            $this->get(route('menu.index', [
                'category' => $slug, 'q' => 'PHÊ SỮA', 'min_price' => 40000, 'max_price' => 40000, 'sort' => 'price_desc',
            ]))->assertOk()->assertSee('Cà phê sữa 100%')->assertDontSee('Cà phê sữa 50%');
            $this->get(route('menu.index', ['category' => $slug, 'q' => '100%']))
                ->assertOk()->assertSee('Cà phê sữa 100%')->assertDontSee('Cà phê sữa 50%');
        } finally {
            $this->primary->rollBack();
        }
    }
}
