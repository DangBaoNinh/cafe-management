<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['code', 'name', 'price_vnd', 'is_active'])]
class Topping extends Model
{
    protected $attributes = ['is_active' => true];

    /** @return BelongsToMany<Product, $this> */
    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'product_toppings');
    }

    /** @return BelongsToMany<Ingredient, $this> */
    public function recipeIngredients(): BelongsToMany
    {
        return $this->belongsToMany(Ingredient::class)->withPivot('quantity');
    }

    /** @param Builder<Topping> $query */
    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where($this->qualifyColumn('is_active'), true);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['price_vnd' => 'integer', 'is_active' => 'boolean'];
    }
}
