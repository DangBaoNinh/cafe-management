<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ProductSize;
use App\Models\Topping;

class InventoryRecipes
{
    /** @return array{quantities: array<int, int>, missing: list<string>} */
    public function snapshot(Order $order): array
    {
        $items = $order->items()->with('toppings')->get();
        $sizes = ProductSize::query()->with('recipeIngredients')
            ->whereKey($items->pluck('product_size_id')->all())->get()->keyBy('id');
        $toppings = Topping::query()->with('recipeIngredients')
            ->whereKey($items->flatMap(fn (OrderItem $item) => $item->toppings->pluck('topping_id'))->unique()->values()->all())
            ->get()->keyBy('id');

        $quantities = [];
        $missing = [];
        foreach ($items as $item) {
            $this->add($quantities, $missing, $sizes->get($item->product_size_id), $item->quantity, $item->product_name.' · '.$item->size_label);
            foreach ($item->toppings as $topping) {
                $this->add($quantities, $missing, $toppings->get($topping->topping_id), $item->quantity, 'Topping '.$topping->topping_name);
            }
        }

        return ['quantities' => $quantities, 'missing' => array_values(array_unique($missing))];
    }

    /**
     * @param  array<int, int>  $quantities
     * @param  list<string>  $missing
     */
    private function add(array &$quantities, array &$missing, ProductSize|Topping|null $option, int $cups, string $label): void
    {
        if ($option === null || $option->recipeIngredients->isEmpty()) {
            $missing[] = $label;

            return;
        }

        foreach ($option->recipeIngredients->pluck('pivot.quantity', 'id') as $id => $quantity) {
            $ingredientId = (int) $id;
            $quantities[$ingredientId] = ($quantities[$ingredientId] ?? 0) + (int) $quantity * $cups;
        }
    }
}
