<?php

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProductManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_management_is_restricted_to_admins(): void
    {
        $category = $this->category();
        $fields = $this->fields($category);
        $product = Product::create($fields);
        $index = route('admin.products.index');
        $store = route('admin.products.store');
        $update = route('admin.products.update', $product);
        $this->get($index)->assertRedirect(route('login'));
        $this->post($store, $fields)->assertRedirect(route('login'));

        foreach ([UserRole::Staff, UserRole::Customer] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]));
            $this->get($index)->assertForbidden();
            $this->post($store, $fields)->assertForbidden();
            $this->patch($update, [...$fields, 'is_active' => '0'])->assertForbidden();
        }

        $this->assertTrue($product->refresh()->is_active);
        $this->assertDatabaseCount('products', 1);
    }

    public function test_an_admin_can_create_a_product_and_replace_its_image(): void
    {
        $disk = Storage::fake('public');
        $this->signInAsAdmin();
        $this->get(route('admin.products.create'))->assertOk()->assertSee('Hãy tạo danh mục trước khi thêm sản phẩm.');
        $category = $this->category();
        $form = $this->get(route('admin.products.create'))->assertOk()->assertSee('multipart/form-data');
        $form->assertSee($category->name);

        $description = '</textarea><script>alert(1)</script>';
        $fields = [...$this->fields($category), 'description' => $description];
        $this->post(route('admin.products.store'), [...$fields, 'image' => UploadedFile::fake()->image('coffee.png')])
            ->assertRedirect(route('admin.products.index'))->assertSessionHasNoErrors();

        $product = Product::where('slug', $fields['slug'])->sole();
        $originalPath = $product->image_path;
        $this->assertStringStartsWith('products/', $originalPath);
        $disk->assertExists($originalPath);
        $this->assertSame($description, $product->description);
        $this->assertTrue($product->allows_sugar);
        $this->assertFalse($product->allows_ice);
        $this->get(route('admin.products.index'))->assertOk()->assertSee($product->name);
        $this->get(route('admin.products.edit', $product))->assertOk()
            ->assertSee($description)->assertDontSee($description, false)->assertSee($disk->url($originalPath));

        $url = route('admin.products.update', $product);
        $this->patch($url, [...$fields, 'description' => '', 'allows_ice' => '1'])->assertSessionHasNoErrors();
        $this->assertNull($product->refresh()->description);
        $this->assertTrue($product->allows_ice);
        $this->assertSame($originalPath, $product->image_path);
        $disk->assertExists($originalPath);

        $otherCategory = Category::create(['name' => 'Trà', 'slug' => 'tra', 'is_active' => false]);
        $this->post($url, [
            ...$fields,
            '_method' => 'PATCH',
            'category_id' => $otherCategory->id,
            'image' => UploadedFile::fake()->image('replacement.jpg'),
        ])->assertRedirect(route('admin.products.index'))->assertSessionHasNoErrors();
        $product->refresh();
        $this->assertSame($otherCategory->id, $product->category_id);
        $this->assertNotSame($originalPath, $product->image_path);
        $disk->assertExists($product->image_path);
        $disk->assertMissing($originalPath);
    }

    public function test_invalid_inputs_do_not_write_products_or_images(): void
    {
        $disk = Storage::fake('public');
        $this->signInAsAdmin();
        $fields = $this->fields($this->category());
        Product::create([...$fields, 'slug' => 'slug-da-dung']);
        $url = route('admin.products.store');

        $this->post($url, [
            ...$fields, 'slug' => 'slug-da-dung', 'category_id' => 999, 'allows_ice' => 'yes',
        ])->assertSessionHasErrors([
            'slug', 'category_id' => 'Lựa chọn danh mục không tồn tại.', 'allows_ice',
        ]);
        $this->post($url, [...$fields, 'slug' => 'Cà phê sữa'])->assertSessionHasErrors('slug');

        $spoofedImage = UploadedFile::fake()->createWithContent('fake.png', 'Not an image');

        foreach ([
            new UploadedFile($spoofedImage->getPathname(), 'fake.png', 'image/png', test: true),
            UploadedFile::fake()->image('coffee.gif'),
            UploadedFile::fake()->image('large.jpg')->size(2049),
        ] as $image) {
            $this->post($url, [...$fields, 'image' => $image])->assertSessionHasErrors('image');
        }

        $this->assertDatabaseCount('products', 1);
        $this->assertSame([], $disk->allFiles());
    }

    public function test_a_database_failure_during_replacement_preserves_the_previous_image(): void
    {
        $disk = Storage::fake('public');
        $this->signInAsAdmin();
        $fields = $this->fields($this->category());
        $originalPath = 'products/original.png';
        $disk->put($originalPath, 'original');
        $product = Product::create([...$fields, 'image_path' => $originalPath]);
        Product::create([...$fields, 'slug' => 'slug-da-dung']);

        // Simulate a collision after request validation, at the database constraint.
        Product::updating(static function (Product $product): void {
            $product->slug = 'slug-da-dung';
        });

        $this->post(route('admin.products.update', $product), [
            ...$fields, '_method' => 'PATCH', 'image' => UploadedFile::fake()->image('replacement.png'),
        ])->assertSessionHasErrors('slug');

        $product->refresh();
        $this->assertSame($fields['slug'], $product->slug);
        $this->assertSame($originalPath, $product->image_path);
        $this->assertSame([$originalPath], $disk->allFiles());
    }

    public function test_stopping_sales_preserves_the_product_and_excludes_it_from_active_products(): void
    {
        $this->signInAsAdmin();
        $fields = $this->fields($this->category());
        $product = Product::create($fields);
        $url = route('admin.products.update', $product);
        $active = Product::active()->inActiveCategory()->whereKey($product->id);
        $this->assertTrue($active->exists());

        $this->patch($url, [...$fields, 'is_active' => '0'])->assertSessionHasNoErrors();
        $this->assertFalse($active->exists());
        $this->assertDatabaseHas('products', [
            'id' => $product->id, 'category_id' => $product->category_id, 'is_active' => false,
        ]);
        $this->get(route('admin.products.index'))->assertOk()->assertSee('Ngừng bán');
        $this->delete($url)->assertMethodNotAllowed();
        $this->assertModelExists($product);
        $this->patch($url, $fields)->assertSessionHasNoErrors();
        $this->assertTrue($active->exists());
    }

    private function signInAsAdmin(): void
    {
        $this->actingAs(User::factory()->create(['role' => UserRole::Admin]));
    }

    private function category(): Category
    {
        return Category::create(['name' => 'Cà phê', 'slug' => 'ca-phe']);
    }

    private function fields(Category $category): array
    {
        return [
            'category_id' => $category->id,
            'name' => 'Cà phê sữa',
            'slug' => 'ca-phe-sua',
            'allows_sugar' => '1',
            'allows_ice' => '0',
            'is_active' => '1',
        ];
    }
}
