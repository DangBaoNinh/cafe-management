<?php

namespace App\Models;

use App\Enums\IngredientUnit;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property IngredientUnit $unit
 * @property int $stock
 */
#[Fillable(['name', 'unit', 'low_threshold'])]
class Ingredient extends Model
{
    protected $attributes = ['stock' => 0, 'stock_version' => 0];

    /** @return HasMany<InventoryMovement, $this> */
    public function movements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'unit' => IngredientUnit::class,
            'stock' => 'integer',
            'low_threshold' => 'integer',
            'stock_version' => 'integer',
        ];
    }
}
