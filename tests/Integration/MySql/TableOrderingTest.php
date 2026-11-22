<?php

namespace Tests\Integration\MySql;

use App\Enums\TableRoundStatus;
use App\Enums\TableSessionStatus;
use App\Enums\UserRole;
use App\Models\Category;
use App\Models\Product;
use App\Models\ReservationSeat;
use App\Models\TableSession;
use App\Models\User;
use App\Services\BestSellingProducts;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TableOrderingTest extends MySqlTestCase
{
    public function test_seat_and_session_locks_serialize_opening_round_processing_and_settlement(): void
    {
        DB::setDefaultConnection($this->primary->getName());
        $this->primary->beginTransaction();

        try {
            $this->actingAs(User::factory()->create(['role' => UserRole::Staff]));
            $seat = ReservationSeat::query()->where('code', 'T2-B05')->sole();
            $path = route('operations.table-sessions.open', $seat->code);
            $this->post($path)->assertStatus(303);
            $this->assertPeerCannotLock('reservation_seats', $seat->id);
            $this->postJson($path)->assertConflict();

            $session = $seat->activeTableSession()->sole();
            // Preserve coverage of settlement for sessions created before prepaid billing.
            $session->billing_mode = 'legacy';
            $session->save();
            $category = Category::create(['name' => 'Menu thử', 'slug' => Str::uuid()->toString()]);
            $product = $category->products()->create(['name' => 'Trà thử', 'slug' => Str::uuid()->toString()]);
            $size = $product->sizes()->create(['code' => 'm', 'label' => 'Vừa', 'price_vnd' => 10000]);
            $submit = route('operations.table-sessions.store', $session);
            $payload = [
                'submission_key' => Str::uuid()->toString(),
                'lines' => [['product_id' => $product->id, 'size_id' => $size->id, 'quantity' => 2]],
            ];
            $this->post($submit, $payload)->assertStatus(303);
            $this->assertPeerCannotLock('table_sessions', $session->id);
            $this->post($this->action($session, 'lock'))->assertStatus(303);
            $this->postJson($submit, [...$payload, 'submission_key' => Str::uuid()->toString()])->assertConflict();
            $this->post($submit, $payload)->assertStatus(303);

            $round = $session->rounds()->sole();
            $advance = route('operations.table-sessions.advance', [$session, $round->id]);
            $this->post($advance, ['expected' => 'pending', 'target' => 'accepted'])->assertStatus(303);
            $this->post($advance, ['expected' => 'accepted', 'target' => 'preparing'])->assertStatus(303);
            $this->post($advance, ['expected' => 'preparing', 'target' => 'ready'])->assertStatus(303);
            $this->postJson($this->action($session, 'bill'))->assertConflict();
            $this->post($advance, ['expected' => 'ready', 'target' => 'served'])->assertStatus(303);
            $this->assertSame(TableRoundStatus::Served, $round->fresh()->status);

            $this->primary->flushQueryLog();
            $this->primary->enableQueryLog();
            $this->post($this->action($session, 'bill'))->assertStatus(303);
            $this->post($this->action($session, 'pay'), ['amount_vnd' => 20000])->assertStatus(303);
            $locks = array_filter(array_column($this->primary->getQueryLog(), 'query'), fn (string $query): bool => str_contains($query, 'table_sessions') && str_contains($query, 'for update'));
            $this->assertCount(2, $locks);
            $this->assertPeerCannotLock('table_sessions', $session->id);
            $paidAt = $session->refresh()->paid_at;
            $this->post($this->action($session, 'pay'), ['amount_vnd' => 20000])->assertStatus(303);
            $this->assertEquals($paidAt, $session->refresh()->paid_at);
            $this->assertSame(TableSessionStatus::Paid, $session->status);
            $this->assertSame('occupied', $seat->fresh()->availability());
            $this->post($this->action($session, 'close'))->assertStatus(303);
            $this->assertSame('free', $seat->fresh()->availability());
            $this->assertContains($product->id, app(BestSellingProducts::class)->topFive(Product::query()->visibleInCatalog())->modelKeys());
        } finally {
            $this->primary->disableQueryLog();
            $this->primary->rollBack();
        }
    }

    private function action(TableSession $session, string $action): string
    {
        return route('operations.table-sessions.update', [$session, $action]);
    }

    private function assertPeerCannotLock(string $table, int $id): void
    {
        try {
            $this->peer->transaction(fn () => $this->peer->table($table)->where('id', $id)->lock('for update nowait')->first());
            $this->fail('The competing MySQL connection acquired the row lock.');
        } catch (QueryException $exception) {
            $this->assertSame(3572, $exception->errorInfo[1]);
        }
    }
}
