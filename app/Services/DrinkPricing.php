<?php

namespace App\Services;

use App\Enums\DrinkLevel;
use App\Models\Product;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class DrinkPricing
{
    /**
     * Price a selection against a freshly loaded public product with activeSizes and activeToppings.
     *
     * @param  array<string, mixed>  $selection
     * @return array{size_id: int, sugar_level: ?string, ice_level: ?string, topping_ids: list<int>, unit_price_vnd: int}
     */
    public function quote(Product $product, array $selection): array
    {
        $validated = validator($selection, [
            'size_id' => ['required', 'integer', 'min:1'],
            'sugar_level' => ['nullable', Rule::requiredIf($product->allows_sugar), Rule::prohibitedIf(! $product->allows_sugar), Rule::enum(DrinkLevel::class)],
            'ice_level' => ['nullable', Rule::requiredIf($product->allows_ice), Rule::prohibitedIf(! $product->allows_ice), Rule::enum(DrinkLevel::class)],
            'topping_ids' => ['bail', 'array', 'list'],
            'topping_ids.*' => ['bail', 'required', 'integer', 'min:1', 'distinct'],
        ], [
            'sugar_level.prohibited' => 'Món này dùng mức đường theo công thức.',
            'ice_level.prohibited' => 'Món này dùng mức đá theo công thức.',
            'topping_ids.array' => 'Danh sách topping không hợp lệ.',
            'topping_ids.list' => 'Danh sách topping không hợp lệ.',
            'topping_ids.*.distinct' => 'Mỗi loại topping chỉ được chọn một lần.',
        ], [
            'size_id' => 'size',
            'sugar_level' => 'mức đường',
            'ice_level' => 'mức đá',
            'topping_ids.*' => 'topping',
        ])->validate();

        $size = $product->activeSizes->find((int) $validated['size_id']);

        if ($size === null) {
            throw ValidationException::withMessages(['size_id' => 'Size không còn khả dụng cho món này. Hãy chọn lại.']);
        }

        $toppingIds = array_map(intval(...), $validated['topping_ids'] ?? []);
        sort($toppingIds, SORT_NUMERIC);
        $toppings = $product->activeToppings->whereIn('id', $toppingIds);

        if ($toppings->count() !== count($toppingIds)) {
            throw ValidationException::withMessages(['topping_ids' => 'Có topping không còn khả dụng cho món này. Hãy chọn lại.']);
        }

        return [
            'size_id' => $size->id,
            'sugar_level' => $product->allows_sugar ? $validated['sugar_level'] : null,
            'ice_level' => $product->allows_ice ? $validated['ice_level'] : null,
            'topping_ids' => $toppingIds,
            'unit_price_vnd' => $size->price_vnd + $toppings->sum('price_vnd'),
        ];
    }
}
