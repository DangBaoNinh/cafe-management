<?php

namespace App\Livewire;

use App\Enums\DrinkLevel;
use App\Models\Product;
use App\Services\DrinkPricing;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Component;

class DrinkCustomizer extends Component
{
    #[Locked]
    public int $productId;

    public mixed $sizeId = null;

    public mixed $sugarLevel = null;

    public mixed $iceLevel = null;

    public mixed $toppingIds = [];

    public function mount(Product $product): void
    {
        $this->productId = $product->id;
        $this->selectDefaults($product);
    }

    public function resetSelection(): void
    {
        $this->selectDefaults($this->availableProduct());
    }

    public function render(DrinkPricing $pricing): View
    {
        $product = $this->availableProduct();
        $quote = null;
        $messages = [];

        if ($product !== null) {
            try {
                $quote = $pricing->quote($product, [
                    'size_id' => $this->sizeId,
                    'sugar_level' => $this->sugarLevel,
                    'ice_level' => $this->iceLevel,
                    'topping_ids' => $this->toppingIds,
                ]);
            } catch (ValidationException $exception) {
                $messages = $exception->validator->errors()->all();
            }
        }

        return view('livewire.drink-customizer', [
            'product' => $product,
            'quote' => $quote,
            'messages' => $messages,
            'levels' => DrinkLevel::cases(),
        ]);
    }

    private function availableProduct(): ?Product
    {
        return Product::visibleInCatalog()
            ->with([
                'activeSizes' => fn (HasMany $sizes) => $sizes->orderBy('price_vnd')->orderBy('id'),
                'activeToppings' => fn (BelongsToMany $toppings) => $toppings->orderBy('name')->orderBy('toppings.id'),
            ])
            ->find($this->productId);
    }

    private function selectDefaults(?Product $product): void
    {
        $size = $product?->activeSizes->first();
        $this->sizeId = $size ? (string) $size->id : null;
        $this->sugarLevel = $product?->allows_sugar ? DrinkLevel::Standard->value : null;
        $this->iceLevel = $product?->allows_ice ? DrinkLevel::Standard->value : null;
        $this->toppingIds = [];
    }
}
