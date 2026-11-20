<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['category_id', 'name', 'slug', 'description', 'image_path', 'is_active', 'allows_sugar', 'allows_ice'])]
class Product extends Model
{
    protected $attributes = [
        'is_active' => true,
        'allows_sugar' => false,
        'allows_ice' => false,
    ];

    /** @return BelongsTo<Category, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /** @param Builder<Product> $query */
    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /** @param Builder<Product> $query */
    #[Scope]
    protected function inActiveCategory(Builder $query): void
    {
        $query->whereHas('category', fn (Builder $category) => $category->active());
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'allows_sugar' => 'boolean', 'allows_ice' => 'boolean'];
    }
}
