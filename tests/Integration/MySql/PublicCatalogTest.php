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
}
