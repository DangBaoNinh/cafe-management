<?php

namespace Tests\Integration\MySql;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RevenueDashboardTest extends MySqlTestCase
{
    public function test_report_uses_vietnamese_midnight_in_mysql_for_filtering_and_grouping(): void
    {
        DB::setDefaultConnection($this->primary->getName());
        $this->primary->beginTransaction();

        try {
            $admin = User::factory()->create(['role' => UserRole::Admin]);
            $customer = User::factory()->create();
            $prefix = 'CF-'.strtoupper(Str::random(12));
            foreach ([
                'BEFORE' => ['2036-12-20 16:59:59', 'cod', 'paid'],
                'START' => ['2036-12-20 17:00:00', 'cod', 'paid'],
                'END' => ['2036-12-21 16:59:59', 'bank_transfer', 'paid'],
                'NEXT' => ['2036-12-21 17:00:00', 'bank_transfer', 'paid'],
                'AFTER' => ['2036-12-22 17:00:00', 'cod', 'paid'],
                'UNPAID' => ['2036-12-21 07:00:00', 'cod', 'pending'],
            ] as $suffix => [$utc, $method, $status]) {
                $order = $customer->orders()->make([
                    'order_number' => $prefix.'-'.$suffix, 'checkout_key' => Str::uuid()->toString(),
                    'request_hash' => hash('sha256', $prefix.$suffix), 'recipient_name' => 'Khách thử', 'phone' => '0901234567',
                    'fulfillment_type' => 'delivery', 'status' => 'completed', 'subtotal_vnd' => 55000,
                    'discount_vnd' => 15000, 'delivery_fee_vnd' => 2000, 'total_vnd' => 42000,
                ]);
                $order->forceFill(['created_at' => $utc, 'completed_at' => $utc])->save();
                $order->payment()->create(['method' => $method, 'status' => $status, 'amount_vnd' => 42000]);
            }

            $this->actingAs($admin)->get(route('admin.dashboard', ['from' => '2036-12-21', 'to' => '2036-12-22']))
                ->assertOk()->assertSee('21/12/2036 – 22/12/2036')->assertDontSee($prefix)
                ->assertViewHas('totals', ['orders' => 3, 'subtotal' => 165000, 'discount' => 45000, 'delivery' => 6000, 'total' => 126000])
                ->assertViewHas('daily', [
                    '2036-12-21' => ['orders' => 2, 'subtotal' => 110000, 'discount' => 30000, 'delivery' => 4000, 'total' => 84000],
                    '2036-12-22' => ['orders' => 1, 'subtotal' => 55000, 'discount' => 15000, 'delivery' => 2000, 'total' => 42000],
                ])
                ->assertViewHas('statuses', fn (array $counts): bool => $counts['completed'] === 4 && array_sum($counts) === 4);
        } finally {
            $this->primary->rollBack();
        }
    }
}
