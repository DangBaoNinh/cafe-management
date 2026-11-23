<?php

namespace Tests\Integration\MySql;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AdminOrderManagementTest extends MySqlTestCase
{
    public function test_local_date_filter_includes_midnight_and_excludes_the_next_midnight(): void
    {
        DB::setDefaultConnection($this->primary->getName());
        $this->primary->beginTransaction();

        try {
            $customer = User::factory()->create();
            $admin = User::factory()->create(['role' => UserRole::Admin]);
            $prefix = 'CF-'.strtoupper(Str::random(12)).'-';
            foreach ([
                'BEFORE' => '2026-11-20 16:59:59', 'START' => '2026-11-20 17:00:00',
                'END' => '2026-11-21 16:59:59', 'AFTER' => '2026-11-21 17:00:00',
            ] as $suffix => $utc) {
                $number = $prefix.$suffix;
                $order = $customer->orders()->create([
                    'order_number' => $number, 'checkout_key' => Str::uuid()->toString(),
                    'request_hash' => hash('sha256', $number), 'recipient_name' => 'Khách thử', 'phone' => '0901234567',
                    'fulfillment_type' => 'pickup', 'status' => 'ready', 'subtotal_vnd' => 40000,
                    'discount_vnd' => 0, 'delivery_fee_vnd' => 0, 'total_vnd' => 40000,
                ]);
                $order->forceFill(['created_at' => $utc])->save();
                $order->payment()->create(['method' => 'cod', 'status' => 'pending', 'amount_vnd' => 40000]);
            }

            $this->actingAs($admin)->get(route('admin.orders.index', [
                'number' => strtolower($prefix), 'from' => '2026-11-21', 'to' => '2026-11-21',
                'status' => 'ready', 'method' => 'cod',
            ]))->assertOk()->assertSeeInOrder([$prefix.'END', $prefix.'START'])
                ->assertDontSee($prefix.'BEFORE')->assertDontSee($prefix.'AFTER');
        } finally {
            $this->primary->rollBack();
        }
    }
}
