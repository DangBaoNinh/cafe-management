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
        $items = $order->items()->with('toppings')->get()->map(fn (OrderItem $item): array => [
            'product_size_id' => $item->product_size_id, 'product_name' => $item->product_name, 'size_label' => $item->size_label,
            'quantity' => $item->quantity, 'toppings' => array_values($item->toppings->map(fn ($topping): array => [
                'id' => $topping->topping_id, 'name' => $topping->topping_name,
            ])->all()),
        ])->all();

        return $this->snapshotForLines(array_values($items));
    }

    /**
     * @param  list<array{product_size_id: int, product_name: string, size_label: string, quantity: int, toppings: list<array{id: int, name: string}>}>  $items
     * @return array{quantities: array<int, int>, missing: list<string>}
     */
    public function snapshotForLines(array $items): array
    {
        $sizes = ProductSize::query()->with('recipeIngredients')
            ->whereKey(array_values(array_unique(array_column($items, 'product_size_id'))))->get()->keyBy('id');
        $toppingIds = collect($items)->flatMap(fn (array $item): array => array_column($item['toppings'], 'id'))->unique()->values()->all();
        $toppings = Topping::query()->with('recipeIngredients')
            ->whereKey($toppingIds)->get()->keyBy('id');

        $quantities = [];
        $missing = [];
        foreach ($items as $item) {
            $this->add($quantities, $missing, $sizes->get($item['product_size_id']), $item['quantity'], $item['product_name'].' · '.$item['size_label']);
            foreach ($item['toppings'] as $topping) {
                $this->add($quantities, $missing, $toppings->get($topping['id']), $item['quantity'], 'Topping '.$topping['name']);
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
