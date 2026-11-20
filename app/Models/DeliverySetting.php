<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/** @property list<string> $eligible_districts */
#[Fillable(['delivery_enabled', 'flat_fee_vnd', 'eligible_districts'])]
class DeliverySetting extends Model
{
    public const SINGLETON_ID = 1;

    public const MAX_FEE_VND = 4294967295;

    public $incrementing = false;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'delivery_enabled' => 'boolean',
            'flat_fee_vnd' => 'integer',
            'eligible_districts' => 'array',
            'version' => 'integer',
        ];
    }
}
