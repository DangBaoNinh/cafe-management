<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property PaymentMethod $method
 * @property PaymentStatus $status
 * @property array{bank_name: string, account_number: string, account_holder: string}|null $transfer_details
 */
#[Fillable(['method', 'status', 'amount_vnd', 'transfer_details'])]
class Payment extends Model
{
    /** @return HasMany<PaymentStatusHistory, $this> */
    public function statusHistories(): HasMany
    {
        return $this->hasMany(PaymentStatusHistory::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'method' => PaymentMethod::class,
            'status' => PaymentStatus::class,
            'amount_vnd' => 'integer',
            'transfer_details' => 'array',
            'paid_at' => 'immutable_datetime',
            'refunded_at' => 'immutable_datetime',
        ];
    }
}
