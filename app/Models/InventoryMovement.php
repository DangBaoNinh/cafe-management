<?php

namespace App\Models;

use App\Enums\InventoryMovementKind;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** @property InventoryMovementKind $kind */
#[Fillable(['actor_id', 'order_id', 'kind', 'quantity_change', 'balance_after', 'reason', 'entry_key'])]
class InventoryMovement extends Model
{
    public const UPDATED_AT = null;

    /** @return BelongsTo<User, $this> */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'kind' => InventoryMovementKind::class,
            'quantity_change' => 'integer',
            'balance_after' => 'integer',
        ];
    }
}
