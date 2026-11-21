<?php

namespace App\Models;

use App\Enums\OrderStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property CarbonImmutable $scheduled_at
 * @property CarbonImmutable $arrival_deadline
 * @property CarbonImmutable|null $arrived_at
 * @property CarbonImmutable|null $used_at
 * @property CarbonImmutable|null $no_show_at
 */
#[Fillable(['code', 'scheduled_at', 'arrival_deadline'])]
class ScheduledPickup extends Model
{
    protected $attributes = ['revision' => 0];

    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /** @return HasMany<PickupFee, $this> */
    public function fees(): HasMany
    {
        return $this->hasMany(PickupFee::class);
    }

    public function isOpen(Order $order): bool
    {
        return $order->status->allowsAdminCancellation()
            && $this->used_at === null
            && $this->no_show_at === null
            && ! $this->hasMissedArrival();
    }

    public function hasMissedArrival(): bool
    {
        return $this->arrived_at === null && now()->gte($this->arrival_deadline);
    }

    public function label(Order $order): string
    {
        return match (true) {
            $this->used_at !== null => 'Đã dùng',
            $this->no_show_at !== null => 'Không đến',
            ! $this->isOpen($order) => 'Hết hiệu lực',
            $order->status === OrderStatus::Pending => 'Chờ xác nhận',
            $this->arrived_at !== null => 'Đã ghi nhận đến · '.$order->status->label(),
            default => $order->status->label(),
        };
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'scheduled_at' => 'immutable_datetime',
            'arrival_deadline' => 'immutable_datetime',
            'arrived_at' => 'immutable_datetime',
            'used_at' => 'immutable_datetime',
            'no_show_at' => 'immutable_datetime',
        ];
    }
}
