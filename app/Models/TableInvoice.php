<?php

namespace App\Models;

use App\Enums\CollectionMethod;
use App\Enums\CounterOrderStatus;
use App\Services\TableRounds;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property CollectionMethod $method
 * @property CounterOrderStatus $order_status
 *
 * @phpstan-import-type Selection from TableRounds
 *
 * @property list<Selection> $selections
 * @property list<array<string, mixed>> $initial_items
 * @property array<string, string>|null $bank_details
 * @property CarbonImmutable|null $paid_at
 */
#[Guarded([])]
class TableInvoice extends Model
{
    protected $attributes = ['status' => 'draft', 'order_status' => 'pending_payment', 'deposit_credit_vnd' => 0, 'deposit_refund_vnd' => 0];

    public function getRouteKeyName(): string
    {
        return 'reference';
    }

    /** @return BelongsTo<TableSession, $this> */
    public function tableSession(): BelongsTo
    {
        return $this->belongsTo(TableSession::class);
    }

    /** @return BelongsTo<TableRound, $this> */
    public function round(): BelongsTo
    {
        return $this->belongsTo(TableRound::class, 'table_round_id');
    }

    /** @return HasMany<PickupFee, $this> */
    public function fees(): HasMany
    {
        return $this->hasMany(PickupFee::class);
    }

    public function getAmountDueVndAttribute(): int
    {
        return max(0, $this->total_vnd - $this->deposit_credit_vnd);
    }

    protected function casts(): array
    {
        return ['method' => CollectionMethod::class, 'order_status' => CounterOrderStatus::class, 'selections' => 'array', 'initial_items' => 'array', 'bank_details' => 'array',
            'subtotal_vnd' => 'integer', 'fees_vnd' => 'integer', 'total_vnd' => 'integer', 'revision' => 'integer', 'deposit_credit_vnd' => 'integer', 'deposit_refund_vnd' => 'integer',
            'paid_at' => 'immutable_datetime', 'payment_reported_at' => 'immutable_datetime'];
    }

    /** @return HasMany<TableInvoiceRevision, $this> */
    public function revisions(): HasMany
    {
        return $this->hasMany(TableInvoiceRevision::class);
    }
}
