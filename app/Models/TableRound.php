<?php

namespace App\Models;

use App\Enums\TableRoundStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property TableRoundStatus $status
 * @property array{quantities: array<int, int>, missing: list<string>} $inventory_snapshot
 */
#[Fillable(['submission_key', 'request_hash', 'entered_by', 'status', 'total_vnd', 'inventory_snapshot'])]
class TableRound extends Model
{
    /** @return BelongsTo<TableSession, $this> */
    public function tableSession(): BelongsTo
    {
        return $this->belongsTo(TableSession::class);
    }

    /** @return HasMany<TableRoundItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(TableRoundItem::class);
    }

    /** @return HasMany<TableRoundEvent, $this> */
    public function events(): HasMany
    {
        return $this->hasMany(TableRoundEvent::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['status' => TableRoundStatus::class, 'inventory_snapshot' => 'array', 'total_vnd' => 'integer'];
    }
}
