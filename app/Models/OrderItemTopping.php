<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['topping_id', 'topping_name', 'unit_price_vnd'])]
class OrderItemTopping extends Model
{
    public $timestamps = false;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['unit_price_vnd' => 'integer'];
    }
}
