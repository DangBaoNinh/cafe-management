<?php

namespace App\Models;

use App\Enums\FulfillmentType;
use App\Enums\OrderStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property FulfillmentType $fulfillment_type
 * @property OrderStatus $status
 * @property array<string, string|null>|null $address_snapshot
 */
#[Fillable([
    'order_number', 'checkout_key', 'request_hash', 'coupon_id', 'coupon_code', 'recipient_name',
    'phone', 'address_snapshot', 'fulfillment_type', 'status', 'subtotal_vnd',
    'discount_vnd', 'delivery_fee_vnd', 'total_vnd',
])]
class Order extends Model
{
    /** @return HasMany<OrderItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /** @return HasOne<Payment, $this> */
    public function payment(): HasOne
    {
        return $this->hasOne(Payment::class);
    }

    /** @return HasMany<OrderStatusHistory, $this> */
    public function statusHistories(): HasMany
    {
        return $this->hasMany(OrderStatusHistory::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'address_snapshot' => 'array',
            'fulfillment_type' => FulfillmentType::class,
            'status' => OrderStatus::class,
            'subtotal_vnd' => 'integer',
            'discount_vnd' => 'integer',
            'delivery_fee_vnd' => 'integer',
            'total_vnd' => 'integer',
            'completed_at' => 'immutable_datetime',
        ];
    }
}
