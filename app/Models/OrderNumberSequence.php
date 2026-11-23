<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** @property int $last_number */
class OrderNumberSequence extends Model
{
    public $timestamps = false;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['last_number' => 'integer'];
    }
}
