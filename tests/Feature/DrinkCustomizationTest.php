<?php

namespace Tests\Feature;

use App\Livewire\DrinkCustomizer;
use App\Models\Category;
use App\Models\Product;
use App\Models\Topping;
use App\Models\User;
use App\Services\DrinkPricing;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use Tests\TestCase;

class DrinkCustomizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_change_size_and_toppings_on_the_public_detail_page(): void
    {
        $product = $this->product(['allows_sugar' => true, 'allows_ice' => true]);
        $large = $product->sizes()->create(['code' => 'l', 'label' => 'Cỡ lớn', 'price_vnd' => 46000]);
        $topping = $this->topping('Thạch cà phê', 7000);
        $product->toppings()->attach($topping);

        $this->get(route('menu.show', $product->slug))->assertOk()->assertSeeLivewire(DrinkCustomizer::class)
            ->assertSee('Giá một ly')->assertSee('38.000 ₫')->assertSee('Thạch cà phê');

        Livewire::test(DrinkCustomizer::class, ['product' => $product])
            ->assertSet('sugarLevel', 'standard')
            ->assertSet('iceLevel', 'standard')
            ->assertViewHas('quote', fn ($quote) => $quote['unit_price_vnd'] === 38000)
            ->set('sizeId', (string) $large->id)
            ->set('toppingIds', [(string) $topping->id])
            ->assertViewHas('quote', fn ($quote) => $quote['unit_price_vnd'] === 53000)
            ->set('sugarLevel', 'extra')
            ->set('iceLevel', 'none')
            ->assertViewHas('quote', fn ($quote) => $quote['unit_price_vnd'] === 53000)
            ->set('toppingIds', [])
            ->assertViewHas('quote', fn ($quote) => $quote['unit_price_vnd'] === 46000);

        $this->assertGuest();
    }

    public function test_quote_normalizes_configuration_and_uses_null_for_fixed_options(): void
    {
        $product = $this->product();
        $first = $this->topping('Thạch', 5000);
        $second = $this->topping('Trân châu', 7000);
        $product->toppings()->attach([$first->id, $second->id]);
        $size = $product->sizes()->sole();

        $quote = app(DrinkPricing::class)->quote($product, [
            'size_id' => (string) $size->id,
            'topping_ids' => [(string) $second->id, $first->id],
            'unit_price_vnd' => 1,
        ]);

        $this->assertSame([
            'size_id' => $size->id,
            'sugar_level' => null,
            'ice_level' => null,
            'topping_ids' => [$first->id, $second->id],
            'unit_price_vnd' => 50000,
        ], $quote);

        Livewire::test(DrinkCustomizer::class, ['product' => $product])
            ->assertDontSeeHtml('id="drink-sugarLevel"')->assertDontSeeHtml('id="drink-iceLevel"')
            ->set('sugarLevel', 'none')->set('iceLevel', 'less')
            ->assertSee('Món này dùng mức đường theo công thức.')
            ->assertSee('Món này dùng mức đá theo công thức.')
            ->assertViewHas('quote', fn ($quote) => $quote === null)
            ->call('resetSelection')->assertViewHas('quote', fn ($quote) => $quote['unit_price_vnd'] === 38000);
    }

    public function test_unavailable_or_duplicate_choices_and_invalid_levels_are_rejected(): void
    {
        $product = $this->product(['allows_ice' => true]);
        $other = $this->product(['slug' => 'mon-khac']);
        $foreignSize = $other->sizes()->sole();
        $foreignTopping = $this->topping('Topping của món khác', 6000);
        $inactive = $this->topping('Topping đã tắt', 6000, false);
        $allowed = $this->topping('Topping được chọn', 5000);
        $other->toppings()->attach($foreignTopping);
        $product->toppings()->attach([$allowed->id, $inactive->id]);

        Livewire::test(DrinkCustomizer::class, ['product' => $product])
            ->assertDontSee($foreignTopping->name)->assertDontSee($inactive->name)
            ->set('sizeId', (string) $foreignSize->id)->assertSee('Size không còn khả dụng cho món này.')
            ->assertViewHas('quote', fn ($quote) => $quote === null)
            ->call('resetSelection')
            ->set('toppingIds', [(string) $foreignTopping->id])->assertSee('Có topping không còn khả dụng cho món này.')
            ->set('toppingIds', [(string) $inactive->id])->assertSee('Có topping không còn khả dụng cho món này.')
            ->set('toppingIds', [$allowed->id, (string) $allowed->id])->assertSee('Mỗi loại topping chỉ được chọn một lần.')
            ->set('toppingIds', 'malformed')->assertSee('Danh sách topping không hợp lệ.')
            ->set('toppingIds', [])
            ->set('iceLevel', 'unexpected')->assertSee('Lựa chọn mức đá không hợp lệ.')
            ->assertViewHas('quote', fn ($quote) => $quote === null);
    }

    public function test_active_choices_and_prices_are_checked_again_on_every_update(): void
    {
        $product = $this->product();
        $original = $product->sizes()->sole();
        $alternative = $product->sizes()->create(['code' => 'l', 'label' => 'Lớn', 'price_vnd' => 46000]);
        $topping = $this->topping('Thạch', 7000);
        $product->toppings()->attach($topping);
        $screen = Livewire::test(DrinkCustomizer::class, ['product' => $product])
            ->set('toppingIds', [(string) $topping->id]);

        $original->update(['price_vnd' => 40000]);
        $screen->call('$refresh')->assertViewHas('quote', fn ($quote) => $quote['unit_price_vnd'] === 47000);

        $original->update(['is_active' => false]);
        $screen->call('$refresh')->assertSee('Size không còn khả dụng cho món này.')->assertViewHas('quote', fn ($quote) => $quote === null)
            ->call('resetSelection')->assertSet('sizeId', (string) $alternative->id)->assertSet('toppingIds', []);

        $screen->set('toppingIds', [(string) $topping->id]);
        $product->toppings()->detach($topping);
        $screen->call('$refresh')->assertSee('Có topping không còn khả dụng cho món này.')->assertViewHas('quote', fn ($quote) => $quote === null);

        $product->category->update(['is_active' => false]);
        $screen->call('$refresh')->assertSee('Món này hiện không còn bán.')->assertViewHas('quote', fn ($quote) => $quote === null);
    }

    public function test_product_identifier_cannot_be_changed_after_mounting(): void
    {
        $product = $this->product();
        $other = $this->product(['slug' => 'mon-khac', 'is_active' => false]);

        $this->expectException(CannotUpdateLockedPropertyException::class);
        Livewire::test(DrinkCustomizer::class, ['product' => $product])->set('productId', $other->id);
    }

    public function test_price_preview_only_reads_database_for_a_customer(): void
    {
        $product = $this->product();
        $topping = $this->topping('Thạch', 5000);
        $product->toppings()->attach($topping);
        $this->actingAs(User::factory()->create());

        DB::enableQueryLog();
        try {
            Livewire::test(DrinkCustomizer::class, ['product' => $product])
                ->set('toppingIds', [$topping->id])
                ->assertViewHas('quote', fn ($quote) => $quote['unit_price_vnd'] === 43000);

            $queries = DB::getQueryLog();
            $this->assertNotEmpty($queries);
            foreach ($queries as $query) {
                $this->assertStringStartsWith('select', strtolower(ltrim($query['query'])));
            }
        } finally {
            DB::disableQueryLog();
            DB::flushQueryLog();
        }
    }

    private function product(array $attributes = []): Product
    {
        $category = Category::firstOrCreate(['slug' => 'ca-phe'], ['name' => 'Cà phê']);
        $product = $category->products()->create(['slug' => 'ca-phe-sua', 'name' => 'Cà phê sữa', ...$attributes]);
        $product->sizes()->create(['code' => 'm', 'label' => 'Cỡ vừa', 'price_vnd' => 38000]);

        return $product;
    }

    private function topping(string $name, int $price, bool $active = true): Topping
    {
        return Topping::create(['code' => 'topping-'.(Topping::count() + 1), 'name' => $name, 'price_vnd' => $price, 'is_active' => $active]);
    }
}
