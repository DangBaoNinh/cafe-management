<?php

namespace Tests\Integration\MySql;

use App\Models\Category;
use App\Models\Topping;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ProductOptionsTest extends MySqlTestCase
{
    public function test_mysql_enforces_size_and_topping_constraints(): void
    {
        DB::setDefaultConnection($this->primary->getName());
        $this->primary->beginTransaction();

        try {
            $suffix = Str::uuid()->toString();
            $category = Category::create(['slug' => $suffix, 'name' => 'Danh mục thử']);
            $product = $category->products()->create(['slug' => 'coffee-'.$suffix, 'name' => 'Cà phê']);
            $other = $category->products()->create(['slug' => 'tea-'.$suffix, 'name' => 'Trà']);
            $fields = ['code' => 'm', 'label' => 'Vừa', 'price_vnd' => 42000];
            $size = $product->sizes()->create($fields);
            $otherSize = $other->sizes()->create($fields);
            $topping = Topping::create(['code' => $suffix, 'name' => 'Thạch', 'price_vnd' => 5000]);
            $product->toppings()->attach($topping);

            $violations = [
                1062 => [
                    fn () => $product->sizes()->create($fields),
                    fn () => $product->toppings()->attach($topping),
                ],
                1264 => [fn () => $product->sizes()->create([...$fields, 'code' => 'l', 'price_vnd' => -1])],
                1451 => [fn () => $topping->delete(), fn () => $other->delete()],
            ];

            foreach ($violations as $expectedCode => $operations) {
                foreach ($operations as $operation) {
                    try {
                        $operation();
                        $this->fail("Expected MySQL error {$expectedCode}.");
                    } catch (QueryException $exception) {
                        $this->assertSame($expectedCode, $exception->errorInfo[1]);
                    }
                }
            }

            $size->update(['is_active' => false]);
            $topping->update(['is_active' => false]);
            $this->assertFalse($product->activeSizes()->exists());
            $this->assertTrue($other->activeSizes()->sole()->is($otherSize));
            $this->assertFalse($product->activeToppings()->exists());
            $this->assertTrue($product->toppings()->sole()->is($topping));
        } finally {
            $this->primary->rollBack();
        }
    }
}
