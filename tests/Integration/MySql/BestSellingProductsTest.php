<?php

namespace Tests\Integration\MySql;

use App\Models\Category;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\BestSellerFixture;

class BestSellingProductsTest extends MySqlTestCase
{
    use BestSellerFixture;

    public function test_home_ranking_uses_vietnamese_midnight_on_mysql(): void
    {
        DB::setDefaultConnection($this->primary->getName());
        $this->travelTo(CarbonImmutable::parse('2036-11-21 17:00:00', 'UTC'));
        $this->primary->beginTransaction();

        try {
            $customer = User::factory()->create();
            $category = Category::create(['name' => 'Đồ uống', 'slug' => Str::random(16)]);
            $first = $this->makeCatalogProduct($category, 'Cà phê biên đầu kỳ');
            $second = $this->makeCatalogProduct($category, 'Cà phê cuối ngày');
            $excluded = $this->makeCatalogProduct($category, 'Cà phê ngoài kỳ');
            $this->recordSale($customer, '2036-10-23 17:00:00', [[$first->sizes()->sole(), 3]], withToppings: true);
            $this->recordSale($customer, '2036-11-22 16:59:59', [[$second->sizes()->sole(), 3]]);
            $this->recordSale($customer, '2036-10-23 16:59:59', [[$excluded->sizes()->sole(), 100]]);
            $this->recordSale($customer, '2036-11-22 17:00:00', [[$excluded->sizes()->sole(), 100]]);

            $this->get(route('home'))->assertOk()->assertSee('Món bán chạy')
                ->assertViewHas('bestSellers', fn ($products) => $products->modelKeys() === [$first->id, $second->id]);
        } finally {
            $this->primary->rollBack();
        }
    }
}
