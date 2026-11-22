<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Ingredient;
use App\Models\ProductSize;
use App\Models\Topping;
use App\Services\InventoryControl;
use App\Support\InventoryQuantity;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class InventoryRecipeController extends Controller
{
    public function __construct(private InventoryControl $control) {}

    public function show(string $kind, string $option): View
    {
        $recipe = $this->option($kind, $option);

        return view('admin.inventory.recipe', [
            'kind' => $kind,
            'option' => $recipe,
            'title' => $recipe instanceof ProductSize ? $recipe->product->name.' · '.$recipe->label : 'Topping '.$recipe->name,
            'back' => $recipe instanceof ProductSize ? route('admin.products.options', $recipe->product) : route('admin.toppings.index'),
            'ingredients' => Ingredient::query()->orderBy('name')->get(),
            'components' => $recipe->recipeIngredients()->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request, string $kind, string $option): RedirectResponse
    {
        $data = $request->validate([
            'ingredient_id' => ['required', 'integer', Rule::exists(Ingredient::class, 'id')],
            'quantity' => ['required', 'string', InventoryQuantity::UNSIGNED_RULE],
        ], [], ['ingredient_id' => 'nguyên liệu', 'quantity' => 'định lượng']);

        $quantity = InventoryQuantity::parse($data['quantity']);
        if ($quantity === 0) {
            throw ValidationException::withMessages(['quantity' => 'Định lượng phải lớn hơn 0.']);
        }

        DB::transaction(function () use ($kind, $option, $data, $quantity): void {
            $this->control->lockForOperation();
            $recipe = $this->option($kind, $option, lock: true);
            $recipe->recipeIngredients()->syncWithoutDetaching([(int) $data['ingredient_id'] => ['quantity' => $quantity]]);
        }, 5);

        return to_route('admin.inventory.recipes.show', [$kind, $option], 303)->with('status', 'Đã lưu định lượng.');
    }

    public function destroy(string $kind, string $option, Ingredient $ingredient): RedirectResponse
    {
        DB::transaction(function () use ($kind, $option, $ingredient): void {
            $this->control->lockForOperation();
            $this->option($kind, $option, lock: true)->recipeIngredients()->detach($ingredient->id);
        }, 5);

        return to_route('admin.inventory.recipes.show', [$kind, $option], 303)->with('status', 'Đã bỏ nguyên liệu khỏi công thức.');
    }

    private function option(string $kind, string $id, bool $lock = false): ProductSize|Topping
    {
        return $kind === 'size'
            ? ProductSize::query()->when($lock, fn ($query) => $query->lockForUpdate())->findOrFail($id)
            : Topping::query()->when($lock, fn ($query) => $query->lockForUpdate())->findOrFail($id);
    }
}
