<?php

namespace Tests\Integration\MySql;

use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Models\Ingredient;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\InventoryOrderFixture;

class RecipeInventoryTest extends MySqlTestCase
{
    use InventoryOrderFixture;

    public function test_preparation_keeps_order_and_stock_locks_until_commit_and_rechecks_the_remaining_stock(): void
    {
        DB::setDefaultConnection($this->primary->getName());
        $this->primary->beginTransaction();

        try {
            $admin = User::factory()->create(['role' => UserRole::Admin]);
            $staff = User::factory()->create(['role' => UserRole::Staff]);
            $customer = User::factory()->create();
            $milk = Ingredient::create(['name' => 'Sữa '.Str::uuid(), 'unit' => 'ml', 'low_threshold' => 0]);
            $coffee = Ingredient::create(['name' => 'Cà phê '.Str::uuid(), 'unit' => 'g', 'low_threshold' => 0]);
            $this->actingAs($admin);
            foreach ([$milk, $coffee] as $ingredient) {
                $this->post(route('admin.inventory.record', $ingredient), [
                    'kind' => 'count', 'quantity' => '3,125', 'stock_version' => 0,
                    'reason' => 'Tồn đầu kỳ', 'entry_key' => Str::uuid()->toString(),
                ])->assertStatus(303);
            }
            $this->inventoryOrder($customer, ['quantities' => [], 'missing' => ['Công thức mẫu']]);
            $this->postJson(route('admin.inventory.enable'))->assertUnprocessable()->assertJsonValidationErrors('inventory_control');
            // Keep the live enable gate and the race test independent of previous test fixtures.
            $this->primary->table('inventory_settings')->where('id', 1)->update(['enabled' => true]);

            $snapshot = ['quantities' => [$coffee->id => 1000, $milk->id => 3125], 'missing' => []];
            $first = $this->inventoryOrder($customer, $snapshot);
            $second = $this->inventoryOrder($customer, $snapshot);
            $start = ['expected_status' => 'confirmed', 'target_status' => 'preparing'];
            $this->primary->enableQueryLog();
            $this->actingAs($staff)->post(route('operations.orders.prepare', $first->order_number), $start)->assertStatus(303);
            $locks = collect($this->primary->getQueryLog())
                ->filter(fn (array $entry): bool => str_contains($entry['query'], 'from `ingredients`') && str_contains($entry['query'], 'for update'));
            $this->primary->disableQueryLog();
            $this->assertSame([[$milk->id], [$coffee->id]], $locks->pluck('bindings')->values()->all());

            $this->peer->beginTransaction();
            try {
                foreach ([['orders', $first->id], ['ingredients', $milk->id], ['ingredients', $coffee->id]] as [$table, $id]) {
                    try {
                        $this->peer->table($table)->where('id', $id)->lock('for update nowait')->first();
                        $this->fail("The competing MySQL connection acquired the {$table} lock.");
                    } catch (QueryException $exception) {
                        $this->assertSame(3572, $exception->errorInfo[1]);
                    }
                }
            } finally {
                $this->peer->rollBack();
            }

            $this->postJson(route('operations.orders.prepare', $first->order_number), $start)->assertConflict()->assertJsonValidationErrors('expected_status');
            $this->postJson(route('operations.orders.prepare', $second->order_number), $start)->assertConflict()->assertJsonValidationErrors('inventory');
            $this->assertSame(OrderStatus::Preparing, $first->refresh()->status);
            $this->assertSame(OrderStatus::Confirmed, $second->refresh()->status);
            $this->assertSame(0, $milk->refresh()->stock);
            $this->assertSame(2125, $coffee->refresh()->stock);
            $this->assertSame(1, $milk->movements()->where('order_id', $first->id)->count());
            $this->assertSame(1, $coffee->movements()->where('order_id', $first->id)->count());
            $this->assertSame(0, $milk->movements()->where('order_id', $second->id)->count());
            $this->assertSame(0, $coffee->movements()->where('order_id', $second->id)->count());
        } finally {
            $this->primary->disableQueryLog();
            $this->primary->rollBack();
        }
    }
}
