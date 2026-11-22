<?php

namespace Tests\Support;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Str;

trait InventoryOrderFixture
{
    /** @param array{quantities: array<int, int>, missing: list<string>}|null $snapshot */
    private function inventoryOrder(User $customer, ?array $snapshot = null): Order
    {
        $order = $customer->orders()->create([
            'order_number' => 'CF-'.Str::ulid(), 'checkout_key' => Str::uuid()->toString(),
            'request_hash' => hash('sha256', 'inventory test'), 'recipient_name' => 'Khách nhận', 'phone' => '0901234567',
            'fulfillment_type' => 'pickup', 'status' => OrderStatus::Confirmed, 'subtotal_vnd' => 40000,
            'discount_vnd' => 0, 'delivery_fee_vnd' => 0, 'total_vnd' => 40000,
        ]);
        $order->inventory_snapshot = $snapshot;
        $order->save();
        $order->payment()->create(['method' => 'cod', 'status' => 'pending', 'amount_vnd' => 40000]);
        $order->statusHistories()->create(['actor_id' => $customer->id, 'to_status' => 'confirmed']);

        return $order;
    }
}
