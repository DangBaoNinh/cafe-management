<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['reference', 'amount_vnd'])]
class DemoPaymentSession extends Model
{
    public const SUCCEEDED = 'succeeded';

    public const FAILED = 'failed';

    /** @return BelongsTo<Payment, $this> */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'amount_vnd' => 'integer',
            'resolved_at' => 'immutable_datetime',
            'applied_at' => 'immutable_datetime',
        ];
    }
}
