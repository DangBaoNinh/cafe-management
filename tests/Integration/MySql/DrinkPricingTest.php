<?php

namespace Tests\Integration\MySql;

use App\Livewire\DrinkCustomizer;
use App\Models\Category;
use App\Models\Topping;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;

class DrinkPricingTest extends MySqlTestCase
{
    public function test_live_price_uses_current_mysql_size_price_and_topping_assignment(): void
    {
        DB::setDefaultConnection($this->primary->getName());
        $this->primary->beginTransaction();

        try {
            $slug = Str::uuid()->toString();
            $category = Category::create(['slug' => $slug, 'name' => 'Cà phê thử']);
            $product = $category->products()->create(['slug' => $slug, 'name' => 'Món thử', 'allows_ice' => true]);
            $size = $product->sizes()->create(['code' => 'm', 'label' => 'Vừa', 'price_vnd' => 47000]);
            $topping = Topping::create(['code' => $slug, 'name' => 'Thạch', 'price_vnd' => 6000]);
            $product->toppings()->attach($topping);

            $screen = Livewire::test(DrinkCustomizer::class, ['product' => $product])
                ->set('iceLevel', 'extra')
                ->set('toppingIds', [(string) $topping->id])
                ->assertViewHas('quote', fn ($quote) => $quote['unit_price_vnd'] === 53000);

            $size->update(['price_vnd' => 53000]);
            $screen->call('$refresh')->assertViewHas('quote', fn ($quote) => $quote['unit_price_vnd'] === 59000);
            $topping->update(['is_active' => false]);
            $screen->call('$refresh')->assertSee('Có topping không còn khả dụng cho món này.')
                ->assertViewHas('quote', fn ($quote) => $quote === null);
        } finally {
            $this->primary->rollBack();
        }
    }
}
