<?php

namespace Tests\Integration\MySql;

use App\Models\Category;
use App\Models\Product;
use App\Services\SaveProduct;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CategoryCatalogTest extends MySqlTestCase
{
    public function test_product_image_replacement_and_sale_status_on_mysql(): void
    {
        DB::setDefaultConnection($this->primary->getName());
        $disk = Storage::fake('public');
        $this->primary->beginTransaction();

        try {
            $slug = 'product-'.Str::uuid();
            $category = Category::create(['name' => 'Danh mục thử', 'slug' => $slug]);
            $fields = ['name' => 'Món thử', 'slug' => $slug, 'category_id' => $category->id];
            $products = app(SaveProduct::class);
            $products->save(new Product, $fields, UploadedFile::fake()->image('coffee.png'));
            $product = Product::where('slug', $slug)->sole();
            $originalPath = $product->image_path;
            $disk->assertExists($originalPath);

            try {
                $products->save(new Product, $fields, UploadedFile::fake()->image('duplicate.png'));
                $this->fail('Expected MySQL to reject the repeated product slug.');
            } catch (UniqueConstraintViolationException $exception) {
                $this->assertSame(1062, $exception->errorInfo[1]);
            }

            $this->assertSame([$originalPath], $disk->allFiles());
            $products->save($product, ['is_active' => false], UploadedFile::fake()->image('replacement.jpg'));
            $product->refresh();
            $this->assertFalse($product->is_active);
            $this->assertFalse(Product::active()->inActiveCategory()->whereKey($product->id)->exists());
            $disk->assertExists($product->image_path);
            $disk->assertMissing($originalPath);
        } finally {
            $this->primary->rollBack();
        }
    }

    public function test_mysql_enforces_unique_slugs_and_preserves_referenced_categories(): void
    {
        DB::setDefaultConnection($this->primary->getName());
        $this->primary->beginTransaction();

        try {
            $slug = 'category-'.Str::uuid();
            $category = Category::create(['name' => 'Danh mục thử', 'slug' => $slug]);
            $product = $category->products()->create(['name' => 'Món thử', 'slug' => "product-{$slug}"]);

            try {
                Category::create(['name' => 'Danh mục trùng', 'slug' => $slug]);
                $this->fail('Expected MySQL to reject the repeated slug.');
            } catch (UniqueConstraintViolationException $exception) {
                $this->assertSame(1062, $exception->errorInfo[1]);
            }

            $this->assertTrue(Product::inActiveCategory()->whereKey($product->id)->exists());
            $category->update(['is_active' => false]);
            $this->assertFalse(Product::inActiveCategory()->whereKey($product->id)->exists());

            try {
                $category->delete();
                $this->fail('Expected MySQL to preserve the referenced category.');
            } catch (QueryException $exception) {
                $this->assertSame(1451, $exception->errorInfo[1]);
            }

            $this->assertModelExists($category);
            $this->assertSame($category->id, $product->fresh()->category->id);
        } finally {
            $this->primary->rollBack();
        }
    }
}
