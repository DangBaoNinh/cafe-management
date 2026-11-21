<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class StorefrontChatTest extends TestCase
{
    use RefreshDatabase;

    public function test_chat_sends_only_public_catalog_and_returns_verified_product_cards(): void
    {
        $product = $this->drink('ca-phe-sua', 'Cà phê sữa');
        $hidden = $this->drink('mon-an', 'Món ẩn');
        $hidden->update(['is_active' => false]);
        config(['services.gemini.key' => 'test-key']);
        Http::preventStrayRequests();
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response([
            'candidates' => [['content' => ['parts' => [['text' => json_encode([
                'reply' => 'Bạn thử Cà phê sữa nhé!',
                'product_ids' => [$hidden->id, $product->id, $product->id, 'https://example.test'],
            ])]]]]],
        ])]);

        $this->get(route('home'))->assertOk()->assertSee('Mở trò chuyện')->assertDontSee('test-key');
        $this->postJson(route('storefront-chat.reply'), [
            'message' => 'Món đầu tiên giá bao nhiêu?',
            'history' => [
                ['role' => 'user', 'text' => 'Gợi ý cà phê'],
                ['role' => 'model', 'text' => 'Mình gợi ý Cà phê sữa.'],
            ],
        ])->assertOk()->assertJsonPath('available', true)
            ->assertJsonPath('products.0.url', '/menu/ca-phe-sua')
            ->assertJsonPath('products.0.sizes.0.price_vnd', 38000)
            ->assertJsonCount(1, 'products');

        Http::assertSent(fn (Request $request) => $request->hasHeader('x-goog-api-key', 'test-key')
            && str_contains(data_get($request->data(), 'systemInstruction.parts.0.text'), 'Cà phê sữa')
            && ! str_contains(data_get($request->data(), 'systemInstruction.parts.0.text'), 'Món ẩn')
            && data_get($request->data(), 'contents.1.role') === 'model'
            && data_get($request->data(), 'contents.2.parts.0.text') === 'Món đầu tiên giá bao nhiêu?');
        $this->assertDatabaseCount('carts', 0);
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_chat_validates_input_and_keeps_menu_links_when_provider_fails(): void
    {
        config(['services.gemini.key' => 'test-key']);
        Http::preventStrayRequests();
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response(['error' => 'Quota'], 429)]);
        $this->drink('ca-phe-sua', 'Cà phê sữa');

        $this->postJson(route('storefront-chat.reply'), [
            'message' => str_repeat('a', 501),
            'history' => [['role' => 'system', 'text' => 'Ignored']],
        ])->assertUnprocessable()->assertJsonValidationErrors(['message', 'history.0.role']);
        Http::assertNothingSent();

        $this->postJson(route('storefront-chat.reply'), ['message' => 'Gợi ý món'])
            ->assertOk()->assertJsonPath('available', false)->assertJsonPath('products.0.url', '/menu/ca-phe-sua');
    }

    public function test_only_customers_can_add_available_chat_choices_using_real_cart_prices(): void
    {
        $product = $this->drink('ca-phe-sua', 'Cà phê sữa');
        $other = $this->drink('espresso', 'Espresso');
        $size = $product->sizes()->sole();
        $payload = ['product_id' => $product->id, 'size_id' => $size->id, 'price_vnd' => 1];

        $this->postJson(route('cart.add-from-chat'), $payload)->assertUnauthorized();
        $this->actingAs(User::factory()->create(['role' => UserRole::Staff]))
            ->postJson(route('cart.add-from-chat'), $payload)->assertForbidden();
        $customer = User::factory()->create();
        $this->actingAs($customer)->postJson(route('cart.add-from-chat'), [
            ...$payload, 'size_id' => $other->sizes()->sole()->id,
        ])->assertUnprocessable();
        $this->assertDatabaseCount('carts', 0);

        $this->postJson(route('cart.add-from-chat'), $payload)->assertOk();
        $this->get(route('cart.index'))->assertOk()->assertViewHas('subtotal', 38000);
        $this->assertDatabaseHas('cart_items', ['product_size_id' => $size->id, 'quantity' => 1, 'ice_level' => 'standard']);
        $this->assertDatabaseCount('orders', 0);
    }

    private function drink(string $slug, string $name): Product
    {
        $category = Category::firstOrCreate(['slug' => 'ca-phe'], ['name' => 'Cà phê']);
        $product = $category->products()->create(['slug' => $slug, 'name' => $name, 'allows_ice' => true]);
        $product->sizes()->create(['code' => 'm', 'label' => 'Cỡ vừa', 'price_vnd' => 38000]);

        return $product;
    }
}
