<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['product_size_id', 'configuration_hash', 'quantity', 'sugar_level', 'ice_level'])]
class CartItem extends Model
{
    public const MAX_QUANTITY = 65535;

    /** @return BelongsTo<ProductSize, $this> */
    public function size(): BelongsTo
    {
        return $this->belongsTo(ProductSize::class, 'product_size_id');
    }

    /** @return BelongsToMany<Topping, $this> */
    public function toppings(): BelongsToMany
    {
        return $this->belongsToMany(Topping::class, 'cart_item_toppings');
    }

    /** @return array<string, mixed> */
    public function selection(): array
    {
        return [
            'size_id' => $this->product_size_id,
            'sugar_level' => $this->sugar_level,
            'ice_level' => $this->ice_level,
            'topping_ids' => $this->toppings->modelKeys(),
        ];
    }
}
