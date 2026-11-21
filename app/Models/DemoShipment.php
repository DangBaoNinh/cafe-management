<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['tracking_number', 'created_by'])]
class DemoShipment extends Model
{
    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['picked_up_at' => 'immutable_datetime', 'delivered_at' => 'immutable_datetime'];
    }
}
