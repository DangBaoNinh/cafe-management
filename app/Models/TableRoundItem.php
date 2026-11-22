<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/** @property list<array{id: int, name: string, price_vnd: int}> $toppings */
#[Fillable(['product_size_id', 'product_name', 'size_label', 'quantity', 'sugar_level', 'ice_level', 'toppings', 'unit_price_vnd', 'line_total_vnd'])]
class TableRoundItem extends Model
{
    public $timestamps = false;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['toppings' => 'array', 'unit_price_vnd' => 'integer', 'line_total_vnd' => 'integer'];
    }
}
