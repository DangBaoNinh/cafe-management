<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;

#[Guarded([])]
class CustomerRefundRequest extends Model
{
    protected function casts(): array
    {
        return ['is_demo' => 'boolean', 'completed_at' => 'immutable_datetime', 'bank_snapshot' => 'encrypted:array', 'amount_vnd' => 'integer'];
    }
}
