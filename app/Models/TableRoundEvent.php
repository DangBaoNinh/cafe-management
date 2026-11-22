<?php

namespace App\Models;

use App\Enums\TableRoundStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['actor_id', 'from_status', 'to_status', 'reason'])]
class TableRoundEvent extends Model
{
    public const UPDATED_AT = null;

    /** @return BelongsTo<User, $this> */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['from_status' => TableRoundStatus::class, 'to_status' => TableRoundStatus::class];
    }
}
