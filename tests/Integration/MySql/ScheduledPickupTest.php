<?php

namespace Tests\Integration\MySql;

use App\Enums\UserRole;
use App\Models\ScheduledPickup;
use App\Models\User;
use App\Services\BookingRules;
use App\Services\ScheduledPickups;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class ScheduledPickupTest extends MySqlTestCase
{
    public function test_mysql_rolls_back_handoff_and_fee_collection_if_using_the_ticket_fails(): void
    {
        DB::setDefaultConnection($this->primary->getName());
        $this->travelTo(CarbonImmutable::parse('2026-11-21 22:36', BookingRules::TIMEZONE)->utc());
        $this->primary->beginTransaction();

        try {
            $customer = User::factory()->create();
            $staff = User::factory()->create(['role' => UserRole::Staff]);
            $key = fake()->uuid();
            $order = $customer->orders()->create([
                'order_number' => 'CF-'.Str::ulid(), 'checkout_key' => $key, 'request_hash' => hash('sha256', $key),
                'recipient_name' => 'Khách thử', 'phone' => '0901234567', 'fulfillment_type' => 'pickup', 'status' => 'ready',
                'subtotal_vnd' => 40000, 'discount_vnd' => 0, 'delivery_fee_vnd' => 0, 'total_vnd' => 40000,
            ]);
            $payment = $order->payment()->create(['method' => 'cod', 'status' => 'pending', 'amount_vnd' => 40000]);
            $pickup = $order->scheduledPickup()->create([
                'code' => strtoupper(bin2hex(random_bytes(8))),
                'scheduled_at' => now()->subMinutes(6),
                'arrival_deadline' => now()->addMinutes(24),
            ]);
            $service = app(ScheduledPickups::class);
            $service->arrive($staff, $pickup->code, 'counter');
            $fee = $pickup->fees()->sole();
            $this->assertSame(2000, $fee->amount_vnd);

            $failSave = true;
            $failure = new RuntimeException('Simulated ticket write failure');
            ScheduledPickup::updating(function (ScheduledPickup $ticket) use (&$failSave, $failure): void {
                if ($failSave && $ticket->isDirty('used_at')) {
                    throw $failure;
                }
            });
            try {
                $service->handoff($staff, $pickup->code, 42000);
                $this->fail('Expected ticket update to fail.');
            } catch (RuntimeException $error) {
                $this->assertSame($failure, $error);
            }
            $failSave = false;
            $this->assertSame('ready', $order->refresh()->status->value);
            $this->assertSame('pending', $payment->refresh()->status->value);
            $this->assertSame('pending', $fee->refresh()->status);
            $this->assertNull($pickup->refresh()->used_at);
            $this->assertSame(0, $order->statusHistories()->count());
            $this->assertSame(0, $payment->statusHistories()->count());

            $service->handoff($staff, $pickup->code, 42000);
            try {
                $service->handoff($staff, $pickup->code, 42000);
                $this->fail('Expected the used ticket to be rejected.');
            } catch (ValidationException $error) {
                $this->assertArrayHasKey('pickup_time', $error->errors());
            }
            $this->assertSame('completed', $order->refresh()->status->value);
            $this->assertSame('paid', $payment->refresh()->status->value);
            $this->assertSame('paid', $fee->refresh()->status);
            $this->assertNotNull($pickup->refresh()->used_at);
            $this->assertSame(1, $order->statusHistories()->count());
            $this->assertSame(1, $payment->statusHistories()->count());
        } finally {
            $this->primary->rollBack();
        }
    }
}
